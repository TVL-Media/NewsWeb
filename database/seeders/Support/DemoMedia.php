<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Sample-data pictures. For each name it uses, in order:
 *  1. storage/app/demo/{name}.jpg: your own photos, if you put any there (not shipped);
 *  2. database/seeders/demo-photos/{name}.jpg: public-domain / CC0 photos shipped with the product
 *     (sources and authors in database/seeders/demo-photos/credits.json and CREDITS.md);
 *  3. a plain placeholder drawn with GD, so seeding never depends on the network.
 */
class DemoMedia
{
    public static function put(string $name, string $targetDir, int $w = 1200, int $h = 800): string
    {
        $target = trim($targetDir, '/').'/'.$name.'.jpg';
        $source = self::source($name);
        $disk = Storage::disk(config('filesystems.media'));

        $disk->put($target, $source ? file_get_contents($source) : self::placeholder($name, $w, $h));

        return $target;
    }

    /** Photo credit shown under a sample story. */
    public static function credit(string $name): string
    {
        if (is_file(storage_path('app/demo/'.$name.'.jpg'))) {
            return 'Sample photo';
        }
        $c = self::credits()[$name] ?? null;
        if ($c && is_file(self::shipped($name))) {
            $who = trim((string) ($c['creator'] ?? '')) ?: 'Unknown photographer';

            return $who.' · '.($c['license'] === 'cc0' ? 'CC0' : 'Public domain');
        }

        return 'Sample image';
    }

    private static function source(string $name): ?string
    {
        foreach ([storage_path('app/demo/'.$name.'.jpg'), self::shipped($name)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function shipped(string $name): string
    {
        return database_path('seeders/demo-photos/'.$name.'.jpg');
    }

    /** @return array<string, array{creator: ?string, license: string, source_url: string}> */
    private static function credits(): array
    {
        static $credits = null;

        return $credits ??= (json_decode((string) @file_get_contents(database_path('seeders/demo-photos/credits.json')), true) ?: []);
    }

    private static function placeholder(string $name, int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        // Dark diagonal gradient seeded by the name, with a soft red glow.
        $seed = crc32($name);
        $base = [30 + $seed % 40, 30 + ($seed >> 8) % 40, 40 + ($seed >> 16) % 50];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img, (int) ($base[0] + 60 * $t), (int) ($base[1] + 20 * $t), (int) ($base[2] + 10 * $t));
            imageline($img, 0, $y, $w, $y, $c);
        }
        $glow = imagecolorallocatealpha($img, 215, 20, 30, 90);
        imagefilledellipse($img, (int) ($w * 0.75), (int) ($h * 0.3), (int) ($w * 0.7), (int) ($h * 0.9), $glow);

        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
