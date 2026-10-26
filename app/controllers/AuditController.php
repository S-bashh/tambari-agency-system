<?php
declare(strict_types=1);

final class AuditController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('audit.view');
        $f = ['q' => $this->str('q'), 'module' => $this->str('module'), 'action' => $this->str('action'), 'user' => $this->int('user'), 'from' => $this->str('from'), 'to' => $this->str('to')];
        $where = ['1=1'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(description LIKE :q OR record_id = :rid OR username LIKE :q)'; $p['q'] = like_escape($f['q']); $p['rid'] = $f['q']; }
        if ($f['module'] !== '') { $where[] = 'module = :m'; $p['m'] = $f['module']; }
        if ($f['action'] !== '') { $where[] = 'action = :a'; $p['a'] = $f['action']; }
        if ($f['user']) { $where[] = 'user_id = :u'; $p['u'] = $f['user']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'created_at >= :from'; $p['from'] = $f['from'] . ' 00:00:00'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'created_at <= :to'; $p['to'] = $f['to'] . ' 23:59:59'; }
        $W = implode(' AND ', $where);
        $pg = paginate((int)db_val("SELECT COUNT(*) FROM audit_logs WHERE $W", $p), 25);
        $rows = db_all("SELECT * FROM audit_logs WHERE $W ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $modules = array_column(db_all('SELECT DISTINCT module FROM audit_logs ORDER BY module'), 'module');
        $actions = array_column(db_all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');
        $users = db_all('SELECT id, full_name FROM users ORDER BY full_name');
        $this->view('audit/index', compact('rows', 'pg', 'f', 'modules', 'actions', 'users') + ['pageTitle' => 'Audit log', 'active' => 'audit']);
    }
}
