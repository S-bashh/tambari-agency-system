<?php
declare(strict_types=1);

/** Database backup written in plain PHP, so it works on XAMPP without needing mysqldump. The output is standard SQL that phpMyAdmin can import. */
final class Backup
{
    public static function dir(): string { return TEAMS_ROOT . '/storage/backups'; }

    /** @return array{file:string,size:int,tables:int,rows:int} */
    public static function createDatabase(): array
    {
        @set_time_limit(0);
        $name = 'teams-db-' . date('Ymd-His') . '.sql';
        $path = self::dir() . '/' . $name;
        $meta = self::dump($path);
        return ['file' => $name, 'size' => (int)filesize($path)] + $meta;
    }

    /** Database dump plus uploaded documents and photos in one ZIP. */
    public static function createFull(): array
    {
        @set_time_limit(0);
        if (!class_exists('ZipArchive')) throw new RuntimeException('The PHP zip extension is not enabled.');
        $stamp = date('Ymd-His');
        $tmp = TEAMS_ROOT . '/storage/tmp/dump-' . $stamp . '.sql';
        $meta = self::dump($tmp);
        $name = 'teams-full-' . $stamp . '.zip';
        $path = self::dir() . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { @unlink($tmp); throw new RuntimeException('Could not create the ZIP file.'); }
        $zip->addFile($tmp, 'database.sql');
        foreach (['documents', 'images'] as $d) {
            $base = TEAMS_ROOT . '/storage/' . $d;
            foreach (glob($base . '/*') ?: [] as $f) {
                if (is_file($f) && basename($f) !== '.htaccess' && basename($f) !== 'index.php') $zip->addFile($f, 'storage/' . $d . '/' . basename($f));
            }
        }
        $zip->addFromString('RESTORE.txt', "TEAMS full backup made " . date('Y-m-d H:i') . "\n\n1. Import database.sql with phpMyAdmin (Import tab) into an empty database.\n2. Copy the folders storage/documents and storage/images from this ZIP into the storage folder of your TEAMS installation.\n3. Make sure config/config.php points to that database.\n");
        $zip->close();
        @unlink($tmp);
        return ['file' => $name, 'size' => (int)filesize($path)] + $meta;
    }

    /** @return array{tables:int,rows:int} */
    private static function dump(string $path): array
    {
        $d = cfg('db');
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], (int)($d['port'] ?? 3306), $d['name']), $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
        $fh = fopen($path, 'wb');
        if (!$fh) throw new RuntimeException('Cannot write to the backup folder.');
        try {
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            $tables = array_map(fn($r) => $r[0], $tables);
            fwrite($fh, "-- TEAMS database backup\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Version: " . TEAMS_VERSION . "\n-- Database: {$d['name']}\n\n");
            fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+01:00';\n\n");
            $totalRows = 0;
            foreach ($tables as $t) {
                $q = '`' . str_replace('`', '``', $t) . '`';
                $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
                fwrite($fh, "-- ----------------------------\n-- Table: $t\n-- ----------------------------\nDROP TABLE IF EXISTS $q;\n$create;\n\n");
                $st = $pdo->query("SELECT * FROM $q");
                $cols = null; $batch = []; $n = 0;
                while ($row = $st->fetch()) {
                    if ($cols === null) $cols = implode(',', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row)));
                    $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row))) . ')';
                    $n++;
                    if (count($batch) >= 200) { fwrite($fh, "INSERT INTO $q ($cols) VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
                }
                $st->closeCursor();
                if ($batch) fwrite($fh, "INSERT INTO $q ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
                fwrite($fh, "\n");
                $totalRows += $n;
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n");
        } finally {
            fclose($fh);
        }
        return ['tables' => count($tables), 'rows' => $totalRows];
    }
}
