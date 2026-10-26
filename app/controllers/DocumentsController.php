<?php
declare(strict_types=1);

final class DocumentsController extends Controller
{
    private const OWNERS = [
        'customer' => ['table' => 'customers', 'view' => 'customers.view', 'route' => 'customers/view', 'label' => 'full_name'],
        'sale' => ['table' => 'sales', 'view' => 'sales.view', 'route' => 'sales/view', 'label' => 'ref'],
        'plot' => ['table' => 'land_plots', 'view' => 'land.view', 'route' => 'land/view', 'label' => 'ref'],
        'tenancy' => ['table' => 'tenancies', 'view' => 'tenancies.view', 'route' => 'tenancies/view', 'label' => 'ref'],
        'property' => ['table' => 'properties', 'view' => 'properties.view', 'route' => 'properties/view', 'label' => 'title'],
    ];

    public function actionIndex(): void
    {
        Auth::require('documents.view');
        $q = $this->str('q');
        $type = $this->str('type');
        $cat = $this->str('category');
        $archived = $this->str('status') === 'archived' && can('documents.delete');
        $where = [$archived ? 'd.archived_at IS NOT NULL' : 'd.archived_at IS NULL'];
        $p = [];
        // only show owner types this user may view
        $allowed = array_keys(array_filter(self::OWNERS, fn($o) => can($o['view'])));
        if (!$allowed) http_error(403, 'You do not have access to any document owners.');
        $in = [];
        foreach ($allowed as $i => $t) { $in[] = ":ot$i"; $p["ot$i"] = $t; }
        $where[] = 'd.owner_type IN (' . implode(',', $in) . ')';
        if ($q !== '') { $where[] = '(d.title LIKE :q OR d.original_name LIKE :q OR d.category LIKE :q)'; $p['q'] = like_escape($q); }
        if (isset(self::OWNERS[$type]) && in_array($type, $allowed, true)) { $where[] = 'd.owner_type = :type'; $p['type'] = $type; }
        if ($cat !== '') { $where[] = 'd.category = :cat'; $p['cat'] = $cat; }
        $W = implode(' AND ', $where);
        $pg = paginate((int)db_val("SELECT COUNT(*) FROM documents d WHERE $W", $p));
        $rows = db_all("SELECT d.*, u.full_name uploader,
              CASE d.owner_type WHEN 'customer' THEN (SELECT full_name FROM customers WHERE id = d.owner_id) WHEN 'property' THEN (SELECT CONCAT(ref, ' · ', title) FROM properties WHERE id = d.owner_id) WHEN 'tenancy' THEN (SELECT ref FROM tenancies WHERE id = d.owner_id) WHEN 'sale' THEN (SELECT ref FROM sales WHERE id = d.owner_id) WHEN 'plot' THEN (SELECT ref FROM land_plots WHERE id = d.owner_id) END owner_label
            FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE $W ORDER BY d.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $cats = array_column(db_all('SELECT DISTINCT category FROM documents ORDER BY category'), 'category');
        $this->view('documents/index', compact('rows', 'pg', 'q', 'type', 'cat', 'cats', 'archived', 'allowed') + ['pageTitle' => 'Documents', 'active' => 'documents']);
    }

    public function actionUpload(): void
    {
        $this->post();
        Auth::require('documents.upload');
        $type = $this->str('owner_type', 'POST');
        $oid = $this->int('owner_id', 'POST');
        $o = self::OWNERS[$type] ?? null;
        if (!$o) http_error(404, 'Unknown document owner.');
        Auth::require($o['view']);
        $owner = db_row("SELECT id, {$o['label']} AS label FROM {$o['table']} WHERE id = ? AND deleted_at IS NULL", [$oid]);
        if (!$owner) http_error(404, 'The record you are attaching to could not be found.');
        $back = fn() => redirect($o['route'], ['id' => $oid]);

        $cat = $this->str('category', 'POST');
        if (!isset(list_doc_categories($type)[$cat])) { flash('danger', 'Choose a document category.'); $back(); }
        $ctx = $type === 'customer' ? $this->str('context', 'POST') : '';
        if ($ctx !== '' && !isset(list_customer_contexts()[$ctx])) $ctx = '';
        $title = mb_substr($this->str('title', 'POST'), 0, 150);
        $notes = mb_substr($this->str('notes', 'POST'), 0, 255);
        $files = Upload::files('files');
        if (!$files) { flash('danger', 'Choose at least one file to upload.'); $back(); }
        if (count($files) > 10) { flash('danger', 'Upload at most 10 files at a time.'); $back(); }

        $ok = 0; $errs = [];
        foreach ($files as $f) {
            $saved = null;
            try {
                $saved = Upload::saveDocument($f);
                $t = (count($files) === 1 && $title !== '') ? $title : mb_substr(pathinfo($saved['original_name'], PATHINFO_FILENAME), 0, 150);
                db_run('INSERT INTO documents (owner_type, owner_id, context, category, title, stored_name, original_name, mime_type, file_size, notes, uploaded_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$type, $oid, $ctx ?: null, $cat, $t, $saved['stored_name'], $saved['original_name'], $saved['mime_type'], $saved['file_size'], $notes ?: null, Auth::id()]);
                Audit::log('upload', 'documents', db_id(), "Uploaded \"{$saved['original_name']}\" ($cat) to {$type} #{$oid} {$owner['label']}");
                $ok++;
            } catch (UploadException $e) {
                $errs[] = $e->getMessage();
            } catch (Throwable $e) {
                if ($saved) @unlink(TEAMS_ROOT . '/storage/documents/' . $saved['stored_name']);
                app_log('Document upload failed: ' . $e->getMessage());
                $errs[] = 'A file could not be saved. Please try again.';
            }
        }
        if ($ok) flash('success', "$ok document" . ($ok > 1 ? 's' : '') . ' uploaded.');
        foreach (array_slice($errs, 0, 3) as $m) flash('danger', $m);
        $back();
    }

    public function actionDownload(): void
    {
        Auth::require('documents.view');
        $d = db_row('SELECT * FROM documents WHERE id = ?', [$this->int('id')]);
        if (!$d) http_error(404, 'That document could not be found.');
        $o = self::OWNERS[$d['owner_type']] ?? null;
        if (!$o) http_error(404, 'That document could not be found.');
        Auth::require($o['view']);
        if ($d['archived_at'] && !can('documents.delete')) http_error(404, 'That document has been archived.');
        $path = TEAMS_ROOT . '/storage/documents/' . basename($d['stored_name']);
        if (!is_file($path)) {
            app_log('Missing file for document #' . $d['id']);
            http_error(404, 'The file for this document is missing from storage. Please contact an administrator.');
        }
        $inlineOk = in_array($d['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        $inline = $inlineOk && $this->int('inline') === 1;
        Audit::log('download', 'documents', $d['id'], 'Downloaded "' . $d['original_name'] . '"');
        $fn = $d['original_name'];
        header('Content-Type: ' . ($inlineOk ? $d['mime_type'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"" . addcslashes(preg_replace('/[^\x20-\x7e]/', '_', $fn), '"\\') . "\"; filename*=UTF-8''" . rawurlencode($fn));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function actionArchive(): void
    {
        $this->post();
        Auth::require('documents.delete');
        $d = db_row('SELECT * FROM documents WHERE id = ? AND archived_at IS NULL', [$this->int('id', 'POST')]);
        if (!$d) http_error(404, 'That document could not be found.');
        db_exec('UPDATE documents SET archived_at = NOW(), archived_by = ? WHERE id = ?', [Auth::id(), (int)$d['id']]);
        Audit::log('archive', 'documents', $d['id'], 'Archived document "' . $d['original_name'] . '"');
        flash('success', 'Document archived.');
        $o = self::OWNERS[$d['owner_type']];
        redirect($o['route'], ['id' => $d['owner_id']]);
    }

    public function actionRestore(): void
    {
        $this->post();
        Auth::require('documents.delete');
        $d = db_row('SELECT * FROM documents WHERE id = ? AND archived_at IS NOT NULL', [$this->int('id', 'POST')]);
        if (!$d) http_error(404, 'That document could not be found.');
        db_exec('UPDATE documents SET archived_at = NULL, archived_by = NULL WHERE id = ?', [(int)$d['id']]);
        Audit::log('restore', 'documents', $d['id'], 'Restored document "' . $d['original_name'] . '"');
        flash('success', 'Document restored.');
        redirect('documents');
    }
}
