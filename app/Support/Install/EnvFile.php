<?php

namespace App\Support\Install;

/** Sets KEY=value lines in a .env file, keeping every other line (and comments) as they are. */
class EnvFile
{
    /** @param array<string, string|int|bool|null> $values */
    public static function set(string $path, array $values): void
    {
        $text = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $text = preg_match($pattern, $text)
                ? preg_replace_callback($pattern, fn () => $line, $text, 1)
                : rtrim($text, "\r\n").($text === '' ? '' : "\n").$line."\n";
        }

        $tmp = $path.'.tmp';
        if (file_put_contents($tmp, $text) === false || ! @rename($tmp, $path)) {
            @unlink($tmp);
            if (file_put_contents($path, $text) === false) {
                throw new \RuntimeException('Could not write '.basename($path).'. Make it writable by the web server.');
            }
        }
    }

    public static function quote(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-]+$/', $value)) {
            return $value;
        }

        // Quote anything with spaces, #, quotes, $ … ($ is escaped so "${…}" is not expanded).
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
