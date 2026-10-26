<?php
declare(strict_types=1);

/** Runs database/migrations/*.sql once each and keeps the permission catalogue in sync. */
final class Migrator
{
    /** @return string[] names of migrations applied */
    public static function run(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(TEAMS_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) continue;
            $sql = (string)file_get_contents($file);
            foreach (self::split($sql) as $stmt) {
                $pdo->exec($stmt);
            }
            $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    }

    /** Migration files that have not been applied yet (used to guard the app after files are replaced but before upgrade). */
    public static function pending(PDO $pdo): array
    {
        $files = array_map('basename', glob(TEAMS_ROOT . '/database/migrations/*.sql') ?: []);
        try {
            $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException) {
            return $files;
        }
        return array_values(array_diff($files, $done));
    }

    private static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(array_map('trim', explode(";\n", $sql)), fn($s) => $s !== ''));
    }

    /** Inserts missing roles/permissions/settings. New permissions go to their default roles only. */
    public static function syncCatalogue(PDO $pdo): void
    {
        $cat = require TEAMS_ROOT . '/config/permissions.php';
        $roleId = [];
        foreach ($cat['roles'] as $slug => [$name, $desc]) {
            $pdo->prepare('INSERT IGNORE INTO roles (slug, name, description) VALUES (?,?,?)')->execute([$slug, $name, $desc]);
            $s = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
            $s->execute([$slug]);
            $roleId[$slug] = (int)$s->fetchColumn();
        }
        foreach ($cat['permissions'] as $key => [$module, $label, $roles]) {
            $s = $pdo->prepare('SELECT id FROM permissions WHERE perm_key = ?');
            $s->execute([$key]);
            $existing = $s->fetchColumn();
            if ($existing) {
                $pdo->prepare('UPDATE permissions SET module = ?, label = ? WHERE id = ?')->execute([$module, $label, $existing]);
                continue;
            }
            $pdo->prepare('INSERT INTO permissions (perm_key, module, label) VALUES (?,?,?)')->execute([$key, $module, $label]);
            $pid = (int)$pdo->lastInsertId();
            foreach (array_merge(['admin'], $roles) as $r) {
                $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)')->execute([$roleId[$r], $pid]);
            }
        }
        foreach ($cat['defaults'] as $k => $v) {
            $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?,?)')->execute([$k, $v]);
        }
        // Seed the default tenancy agreement template once (only when the table exists and is empty)
        try {
            if ((int)$pdo->query('SELECT COUNT(*) FROM agreement_templates')->fetchColumn() === 0) {
                $html = (string)file_get_contents(TEAMS_ROOT . '/config/default_agreement.html');
                $pdo->prepare('INSERT INTO agreement_templates (name, body_html, is_default, is_active) VALUES (?,?,1,1)')->execute(['Standard Tenancy Agreement (Kano)', $html]);
            }
        } catch (PDOException) {
            // table not created yet on a very old schema; the next upgrade run will seed it
        }
    }
}
