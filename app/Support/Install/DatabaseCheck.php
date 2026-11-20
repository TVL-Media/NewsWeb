<?php

namespace App\Support\Install;

use PDO;

/** "Test connection" in the installer: can we log in, is it MySQL 8 / MariaDB 10.4+, is the database empty? */
class DatabaseCheck
{
    /**
     * @param array{host: string, port: int|string, database: string, username: string, password: ?string} $db
     * @return array{ok: bool, message: string}
     */
    public static function test(array $db): array
    {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']),
                $db['username'], (string) ($db['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => self::friendly($e)];
        }

        $version = (string) $pdo->query('select version()')->fetchColumn();
        $maria = stripos($version, 'mariadb') !== false;
        $number = preg_replace('/[^0-9.].*$/', '', $version);
        if ($maria ? version_compare($number, '10.4', '<') : version_compare($number, '8.0', '<')) {
            return ['ok' => false, 'message' => "This server runs {$version}. NextGenNews needs MySQL 8.0+ or MariaDB 10.4+."];
        }

        $tables = $pdo->query('show tables')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables) {
            // Our own tables from an install that stopped half-way can be finished; anything else cannot be touched.
            $ours = in_array('migrations', $tables, true) && in_array('admins', $tables, true);
            $hasAdmins = $ours && (int) $pdo->query('select count(*) from admins')->fetchColumn() > 0;
            if (! $ours || $hasAdmins) {
                return ['ok' => false, 'message' => 'This database already has '.count($tables).' tables'.($hasAdmins ? ' and an admin account' : '').'. Choose an empty database: the installer never changes existing data.'];
            }

            return ['ok' => true, 'message' => 'Connected. An unfinished install was found in this database; it will be completed.'];
        }

        return ['ok' => true, 'message' => 'Connected to '.$db['database'].' ('.($maria ? 'MariaDB ' : 'MySQL ').$number.'). The database is empty.'];
    }

    /** Plain-English reasons for the usual connection errors. */
    public static function friendly(\Throwable $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, '[1045]') => 'The username or password is wrong.',
            str_contains($msg, '[1049]') => 'That database does not exist. Create it first (for example in phpMyAdmin or your hosting panel), then try again.',
            str_contains($msg, '[1044]') => 'This user is not allowed to use that database. Give the user all privileges on it.',
            str_contains($msg, '[2002]'), str_contains($msg, '[2003]'), str_contains($msg, 'getaddrinfo') => 'Could not reach the database server. Check the host and port (usually 127.0.0.1 or localhost, port 3306).',
            str_contains($msg, 'could not find driver') => 'The pdo_mysql PHP extension is missing.',
            default => 'Could not connect: '.preg_replace('/\s*\(Connection: .*$/s', '', $msg),
        };
    }
}
