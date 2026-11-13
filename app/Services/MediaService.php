<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Uploads, MIME sniffing, WebP variants and media metadata (plan §10.5). */
class MediaService
{
    public const TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'audio' => ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav', 'audio/ogg'],
        'caption' => ['text/vtt', 'text/plain', 'application/x-subrip'],
        'document' => ['application/pdf'],
    ];

    public const VARIANTS = ['thumb' => 320, 'card' => 720, 'hero' => 1440];

    public function store(UploadedFile $file, ?string $wantKind, ?int $adminId): Media
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream';
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['vtt', 'srt'], true) && str_starts_with($mime, 'text/')) {
            $mime = $ext === 'vtt' ? 'text/vtt' : 'application/x-subrip';
        }
        $kind = collect(self::TYPES)->search(fn ($list) => in_array($mime, $list, true));
        if (! $kind) {
            throw ValidationException::withMessages(['file' => "This file type ({$mime}) is not allowed."]);
        }
        if ($wantKind && $wantKind !== $kind) {
            throw ValidationException::withMessages(['file' => "Please upload a {$wantKind} file (this looks like a {$kind})."]);
        }

        $maxMb = match ($kind) {
            'video' => (int) setting('content.max_video_mb', 1024), 'audio' => (int) setting('content.max_audio_mb', 300), 'image' => (int) setting('content.max_image_mb', 10), default => 5
        };
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => "File is larger than {$maxMb} MB."]);
        }

        $dir = $kind.'/'.now()->format('Y/m');
        $safeExt = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'audio/mpeg', 'audio/mp3' => 'mp3', 'text/vtt' => 'vtt', 'application/x-subrip' => 'srt', 'application/pdf' => 'pdf', default => $ext ?: 'bin'
        };
        $disk = config('filesystems.media', 'public');
        $path = $file->storeAs($dir, Str::random(32).'.'.$safeExt, $disk);

        $m = Media::create([
            'disk' => $disk, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 250), 'mime_type' => $mime,
            'kind' => $kind, 'size_bytes' => $file->getSize(), 'provider' => 'upload', 'uploaded_by' => $adminId,
            'alt_text' => $kind === 'image' ? Str::of(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->replace(['-', '_'], ' ')->limit(120) : null,
        ]);

        if ($kind === 'image') {
            $this->imageVariants($m);
        }
        if (in_array($kind, ['video', 'audio'], true)) {
            $this->probe($m);
        }

        return $m;
    }

    /** WebP variants with GD (thumb 320, card 720, hero 1440). */
    public function imageVariants(Media $m): void
    {
        if (config("filesystems.disks.{$m->disk}.driver") !== 'local') {
            return; // cloud disk (S3): the CDN serves the original
        }
        $full = Storage::disk($m->disk)->path($m->path);
        $info = @getimagesize($full);
        if (! $info) {
            return;
        }
        [$w, $h] = $info;
        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($full), 'image/png' => @imagecreatefrompng($full), 'image/webp' => @imagecreatefromwebp($full), 'image/gif' => @imagecreatefromgif($full), default => null
        };
        $conv = [];
        if ($src && function_exists('imagewebp')) {
            foreach (self::VARIANTS as $name => $maxW) {
                $nw = min($w, $maxW);
                $nh = (int) round($h * $nw / max(1, $w));
                $dst = imagecreatetruecolor($nw, $nh);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                $p = preg_replace('/\.\w+$/', "-{$name}.webp", $m->path);
                ob_start();
                imagewebp($dst, null, 82);
                Storage::disk($m->disk)->put($p, ob_get_clean());
                imagedestroy($dst);
                $conv[$name] = $p;
            }
            imagedestroy($src);
        }
        $m->update(['width' => $w, 'height' => $h, 'conversions' => $conv]);
    }

    /** Duration + poster frame via FFmpeg when installed (optional dependency). */
    public function probe(Media $m): void
    {
        if (config("filesystems.disks.{$m->disk}.driver") !== 'local') {
            return;
        }
        $full = Storage::disk($m->disk)->path($m->path);
        $ffprobe = trim((string) @shell_exec('command -v ffprobe 2>/dev/null'));
        if ($ffprobe) {
            $out = @shell_exec(escapeshellcmd($ffprobe).' -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($full));
            if (is_numeric(trim((string) $out))) {
                $m->duration_seconds = (int) round((float) $out);
            }
        }
        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg && $m->kind === 'video') {
            $poster = preg_replace('/\.\w+$/', '-poster.jpg', $m->path);
            @shell_exec(escapeshellcmd($ffmpeg).' -y -ss 1 -i '.escapeshellarg($full).' -frames:v 1 -vf scale=720:-2 '.escapeshellarg(Storage::disk($m->disk)->path($poster)).' 2>/dev/null');
            if (Storage::disk($m->disk)->exists($poster)) {
                $m->conversions = array_merge($m->conversions ?? [], ['poster' => $poster]);
            }
        }
        $m->save();
    }
}
