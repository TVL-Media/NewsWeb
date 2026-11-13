<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['conversions' => 'array'];
    }

    public function url(?string $variant = null): ?string
    {
        if ($this->provider !== 'upload') {
            return $this->external_url;
        }
        $path = $variant && isset($this->conversions[$variant]) ? $this->conversions[$variant] : $this->path;

        return $path ? $this->diskUrl($path) : null;
    }

    /** Public-disk files use the current host (works behind any APP_URL); cloud disks use their own URL. */
    private function diskUrl(string $path): string
    {
        return $this->disk === 'public' ? asset('storage/'.$path) : Storage::disk($this->disk)->url($path);
    }

    public function thumbUrl(): ?string
    {
        if ($this->kind === 'image') {
            return $this->url('thumb');
        }
        if (isset($this->conversions['poster'])) {
            return $this->diskUrl($this->conversions['poster']);
        }

        return null;
    }

    /** Stories whose photo is this file. */
    public function usageCount(): int
    {
        return $this->path ? Story::where('photo_path', $this->path)->count() : 0;
    }

    public function humanSize(): string
    {
        $b = $this->size_bytes;
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < 3) {
            $b /= 1024;
            $i++;
        }

        return round($b, 1).' '.$u[$i];
    }
}
