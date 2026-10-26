<?php
declare(strict_types=1);

/** Tenants / Landlords / Guarantors are filtered views of the one customer database. */
final class RoleListController extends Controller
{
    private const PERM = ['tenant' => 'tenants.view', 'landlord' => 'landlords.view', 'guarantor' => 'guarantors.view'];
    private const ROUTE = ['tenant' => 'tenants', 'landlord' => 'landlords', 'guarantor' => 'guarantors'];

    public function __construct(private string $role) {}

    public function actionIndex(): void
    {
        Auth::require(self::PERM[$this->role]);
        $q = $this->str('q');
        $p = ['role' => $this->role];
        $where = ['c.deleted_at IS NULL'];
        if ($q !== '') {
            $w = '(c.full_name LIKE :q OR c.ref LIKE :q OR c.email LIKE :q OR c.phone LIKE :q';
            $p['q'] = like_escape($q);
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if (strlen($digits) >= 4) { $w .= ' OR c.phone_norm LIKE :pn'; $p['pn'] = '%' . $digits . '%'; }
            $where[] = $w . ')';
        }
        $extra = match ($this->role) {
            'tenant'    => '(SELECT COUNT(*) FROM tenant_guarantors tg JOIN tenants t ON t.id = tg.tenant_id WHERE t.customer_id = c.id)',
            'landlord'  => '(SELECT COUNT(*) FROM properties pr WHERE pr.landlord_id = c.id AND pr.deleted_at IS NULL)',
            default     => '(SELECT COUNT(*) FROM tenant_guarantors tg JOIN guarantors g ON g.id = tg.guarantor_id WHERE g.customer_id = c.id)',
        };
        $guarantorFilter = $this->role === 'tenant' && $this->str('guarantor') === 'none';
        if ($guarantorFilter) $where[] = "$extra = 0";
        $W = implode(' AND ', $where);
        $base = "FROM customers c JOIN customer_roles cr ON cr.customer_id = c.id AND cr.role = :role WHERE $W";
        $pg = paginate((int)db_val("SELECT COUNT(*) $base", $p));
        $rows = db_all("SELECT c.id, c.ref, c.full_name, c.phone, c.email, c.occupation, c.employer_name, c.address, $extra AS n $base ORDER BY c.full_name LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $this->view('customers/role_list', [
            'role' => $this->role, 'rows' => $rows, 'pg' => $pg, 'q' => $q, 'guarantorFilter' => $guarantorFilter,
            'route' => self::ROUTE[$this->role], 'meta' => list_roles()[$this->role],
            'pageTitle' => ucfirst($this->role) . 's', 'active' => self::ROUTE[$this->role],
        ]);
    }
}
