<?php
declare(strict_types=1);

final class BackupsController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('backups.manage');
        $rows = db_all('SELECT b.*, u.full_name creator FROM backups b LEFT JOIN users u ON u.id = b.created_by WHERE b.deleted_at IS NULL ORDER BY b.id DESC LIMIT 100');
        foreach ($rows as &$r) $r['exists'] = is_file(Backup::dir() . '/' . basename($r['filename']));
        $this->view('backups/index', ['rows' => $rows, 'zip' => class_exists('ZipArchive'), 'dbName' => (string)(cfg('db')['name'] ?? 'teams'),
            'last' => db_val('SELECT MAX(created_at) FROM backups WHERE deleted_at IS NULL'), 'pageTitle' => 'Backups', 'active' => 'backups']);
    }

    public function actionCreate(): void
    {
        $this->post();
        Auth::require('backups.manage');
        $kind = $this->str('kind', 'POST') === 'full' ? 'full' : 'database';
        try {
            $b = $kind === 'full' ? Backup::createFull() : Backup::createDatabase();
        } catch (Throwable $e) {
            app_log('Backup failed: ' . $e->getMessage());
            flash('danger', $kind === 'full' && !class_exists('ZipArchive') ? 'Full backup needs the PHP zip extension (enable extension=zip in php.ini). A database backup still works.' : 'The backup could not be created. Check that storage/backups is writable.');
            redirect('backups');
        }
        db_run('INSERT INTO backups (filename, kind, file_size, table_count, row_count, note, created_by) VALUES (?,?,?,?,?,?,?)', [$b['file'], $kind, $b['size'], $b['tables'], $b['rows'], mb_substr($this->str('note', 'POST'), 0, 255) ?: null, Auth::id()]);
        Audit::log('backup', 'backups', db_id(), "Created $kind backup {$b['file']} (" . fmt_bytes($b['size']) . ", {$b['tables']} tables, {$b['rows']} rows)");
        flash('success', 'Backup created: ' . $b['file'] . '. Download it and keep a copy on another device.');
        redirect('backups');
    }

    public function actionDownload(): void
    {
        Auth::require('backups.manage');
        $b = db_row('SELECT * FROM backups WHERE id = ? AND deleted_at IS NULL', [$this->int('id')]);
        $path = $b ? Backup::dir() . '/' . basename($b['filename']) : '';
        if (!$b || !is_file($path)) http_error(404, 'That backup file could not be found.');
        Audit::log('download', 'backups', $b['id'], 'Downloaded backup ' . $b['filename']);
        header('Content-Type: ' . ($b['kind'] === 'full' ? 'application/zip' : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . basename($b['filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function actionDelete(): void
    {
        $this->post();
        Auth::require('backups.manage');
        $b = db_row('SELECT * FROM backups WHERE id = ? AND deleted_at IS NULL', [$this->int('id', 'POST')]);
        if (!$b) http_error(404, 'That backup could not be found.');
        @unlink(Backup::dir() . '/' . basename($b['filename']));
        db_exec('UPDATE backups SET deleted_at = NOW() WHERE id = ?', [(int)$b['id']]);
        Audit::log('delete', 'backups', $b['id'], 'Deleted backup file ' . $b['filename']);
        flash('success', 'Backup file deleted.');
        redirect('backups');
    }
}
