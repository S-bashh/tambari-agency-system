<?php
declare(strict_types=1);

final class SearchController extends Controller
{
    public function actionIndex(): void
    {
        $q = $this->str('q');
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $like = like_escape($q);
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if (can('customers.view')) {
                $w = '(c.full_name LIKE :q OR c.ref LIKE :q OR c.email LIKE :q OR c.phone LIKE :q OR c.phone_alt LIKE :q' . (strlen($digits) >= 4 ? ' OR c.phone_norm LIKE :pn' : '') . ')';
                $p = ['q' => $like] + (strlen($digits) >= 4 ? ['pn' => '%' . $digits . '%'] : []);
                $groups[] = ['People', 'bi-people', url('customers', ['q' => $q]), db_all("SELECT c.id, c.full_name title, CONCAT_WS(' · ', c.ref, c.phone, c.email) sub, (SELECT GROUP_CONCAT(role) FROM customer_roles WHERE customer_id = c.id) roles
                    FROM customers c WHERE c.deleted_at IS NULL AND $w ORDER BY c.full_name LIMIT 8", $p), 'customers/view', (int)db_val("SELECT COUNT(*) FROM customers c WHERE c.deleted_at IS NULL AND $w", $p)];
            }
            if (can('properties.view')) {
                $w = '(p.ref LIKE :q OR p.title LIKE :q OR p.address LIKE :q OR p.area LIKE :q OR p.lga LIKE :q)';
                $groups[] = ['Properties', 'bi-buildings', url('properties', ['q' => $q]), db_all("SELECT p.id, p.title title, CONCAT_WS(' · ', p.ref, p.area, p.status) sub, NULL roles FROM properties p WHERE p.deleted_at IS NULL AND $w ORDER BY p.id DESC LIMIT 8", ['q' => $like]),
                    'properties/view', (int)db_val("SELECT COUNT(*) FROM properties p WHERE p.deleted_at IS NULL AND $w", ['q' => $like])];
            }
            if (can('tenancies.view')) {
                $w = '(t.ref LIKE :q OR tc.full_name LIKE :q OR p.ref LIKE :q OR p.title LIKE :q)';
                $from = 'FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id WHERE t.deleted_at IS NULL AND ' . $w;
                $groups[] = ['Tenancies', 'bi-house-check', url('tenancies', ['q' => $q]), db_all("SELECT t.id, CONCAT(t.ref, ' · ', tc.full_name) title, CONCAT_WS(' · ', p.title, t.status, DATE_FORMAT(t.end_date,'%d/%m/%Y')) sub, NULL roles $from ORDER BY t.id DESC LIMIT 8", ['q' => $like]),
                    'tenancies/view', (int)db_val("SELECT COUNT(*) $from", ['q' => $like])];
            }
            if (can('payments.view')) {
                $w = '(pm.txn_id LIKE :q OR r.receipt_no LIKE :q OR pc.full_name LIKE :q OR pm.reference LIKE :q)';
                $from = 'FROM payments pm JOIN customers pc ON pc.id = pm.customer_id LEFT JOIN receipts r ON r.payment_id = pm.id WHERE ' . $w;
                $groups[] = ['Payments & receipts', 'bi-cash-coin', url('payments', ['q' => $q]), db_all("SELECT pm.id, CONCAT(COALESCE(r.receipt_no, pm.txn_id), ' · ', pc.full_name) title, CONCAT_WS(' · ', pm.txn_id, CONCAT('₦', FORMAT(pm.amount, 2)), pm.status) sub, NULL roles $from ORDER BY pm.id DESC LIMIT 8", ['q' => $like]),
                    'payments/view', (int)db_val("SELECT COUNT(*) $from", ['q' => $like])];
            }
            if (can('sales.view')) {
                $w = '(s.ref LIKE :q OR bc.full_name LIKE :q OR pr.title LIKE :q OR lp.plot_number LIKE :q OR lp.ref LIKE :q)';
                $from = 'FROM sales s JOIN customers bc ON bc.id = s.buyer_id LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id WHERE ' . $w;
                $groups[] = ['Sales', 'bi-tag', url('sales', ['q' => $q]), db_all("SELECT s.id, CONCAT(s.ref, ' · ', bc.full_name) title, CONCAT_WS(' · ', COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)), CONCAT('₦', FORMAT(s.sale_price, 2)), s.status) sub, NULL roles $from ORDER BY s.id DESC LIMIT 8", ['q' => $like]),
                    'sales/view', (int)db_val("SELECT COUNT(*) $from", ['q' => $like])];
            }
            if (can('land.view')) {
                $w = '(lp.ref LIKE :q OR lp.plot_number LIKE :q OR lp.area LIKE :q OR e.name LIKE :q)';
                $from = 'FROM land_plots lp LEFT JOIN land_estates e ON e.id = lp.estate_id WHERE lp.deleted_at IS NULL AND ' . $w;
                $groups[] = ['Land & plots', 'bi-map', url('land', ['q' => $q]), db_all("SELECT lp.id, CONCAT(lp.ref, ' · Plot ', lp.plot_number, IF(lp.block_number <> '', CONCAT(', Block ', lp.block_number), '')) title, CONCAT_WS(' · ', e.name, lp.area, lp.status) sub, NULL roles $from ORDER BY lp.id DESC LIMIT 8", ['q' => $like]),
                    'land/view', (int)db_val("SELECT COUNT(*) $from", ['q' => $like])];
            }
            if (can('documents.view') && (can('customers.view') || can('properties.view'))) {
                $w = '(d.title LIKE :q OR d.original_name LIKE :q OR d.category LIKE :q)';
                $groups[] = ['Documents', 'bi-folder2-open', url('documents', ['q' => $q]), db_all("SELECT d.owner_id id, d.owner_type, d.title title, CONCAT_WS(' · ', d.category, d.original_name) sub, NULL roles FROM documents d WHERE d.archived_at IS NULL AND $w ORDER BY d.id DESC LIMIT 8", ['q' => $like]),
                    'documents', (int)db_val("SELECT COUNT(*) FROM documents d WHERE d.archived_at IS NULL AND $w", ['q' => $like])];
            }
        }
        $this->view('search/index', ['q' => $q, 'groups' => $groups, 'pageTitle' => 'Search', 'active' => 'search']);
    }
}
