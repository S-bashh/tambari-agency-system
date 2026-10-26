<?php
declare(strict_types=1);

final class RolesController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('roles.manage');
        $roles = db_all('SELECT * FROM roles ORDER BY id');
        $perms = db_all('SELECT * FROM permissions ORDER BY module, id');
        $grants = [];
        foreach (db_all('SELECT role_id, permission_id FROM role_permissions') as $g) $grants[$g['role_id']][$g['permission_id']] = true;
        $counts = array_column(db_all('SELECT role_id, COUNT(*) n FROM users WHERE is_active = 1 GROUP BY role_id'), 'n', 'role_id');
        $this->view('roles/index', compact('roles', 'perms', 'grants', 'counts') + ['pageTitle' => 'Roles & permissions', 'active' => 'roles']);
    }

    public function actionSave(): void
    {
        $this->post();
        Auth::require('roles.manage');
        $roles = db_all("SELECT id, slug, name FROM roles WHERE slug <> 'admin'");
        $permIds = array_map('intval', array_column(db_all('SELECT id FROM permissions'), 'id'));
        $permKey = array_column(db_all('SELECT id, perm_key FROM permissions'), 'perm_key', 'id');
        $post = (array)($_POST['perm'] ?? []);
        db()->beginTransaction();
        try {
            foreach ($roles as $r) {
                $before = array_map('intval', array_column(db_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [(int)$r['id']]), 'permission_id'));
                $after = array_values(array_intersect($permIds, array_map('intval', (array)($post[$r['id']] ?? []))));
                $add = array_diff($after, $before);
                $rem = array_diff($before, $after);
                foreach ($rem as $pid) db_exec('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?', [(int)$r['id'], $pid]);
                foreach ($add as $pid) db_exec('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [(int)$r['id'], $pid]);
                if ($add || $rem) {
                    Audit::log('permission_change', 'roles', $r['id'], "Changed permissions for role {$r['name']}: +" . count($add) . ' / -' . count($rem),
                        ['granted' => array_map(fn($i) => $permKey[$i], $add), 'revoked' => array_map(fn($i) => $permKey[$i], $rem)]);
                }
            }
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Permissions saved. They apply immediately.');
        redirect('roles');
    }
}
