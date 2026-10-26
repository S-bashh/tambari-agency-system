<?php
declare(strict_types=1);

final class AgreementsController extends Controller
{
    private function agreement(int $id): array
    {
        Auth::require('agreements.view');
        $a = db_row('SELECT * FROM agreements WHERE id = ?', [$id]);
        if (!$a) http_error(404, 'That agreement could not be found.');
        return $a;
    }

    /* ---------- generate ---------- */
    public function actionGenerate(): void
    {
        Auth::require('agreements.generate');
        $t = $this->tenancy($this->int('tenancy'));
        $templates = db_all('SELECT id, name, is_default FROM agreement_templates WHERE is_active = 1 ORDER BY is_default DESC, name');
        if (!$templates) {
            flash('danger', 'There is no active agreement template. Ask an administrator to create one.');
            redirect('tenancies/view', ['id' => $t['id']]);
        }
        $cat = (string)db_val('SELECT category FROM properties WHERE id = ?', [(int)$t['property_id']]);
        form_set(['template_id' => $templates[0]['id'], 'use_purpose' => ['residential' => 'residential', 'commercial' => 'commercial', 'industrial' => 'industrial', 'land' => 'agricultural / building'][$cat] ?? 'residential'], []);
        $missing = [];
        if (!$t['landlord_address']) $missing[] = 'landlord address';
        if (!$t['tenant_address']) $missing[] = 'tenant address';
        $this->view('agreements/generate', ['t' => $t, 'templates' => $templates, 'missing' => $missing, 'pageTitle' => 'Generate agreement', 'active' => 'tenancies']);
    }

    private function tenancy(int $id): array
    {
        $t = db_row("SELECT t.*, tc.full_name tenant_name, tc.address tenant_address, tc.town tenant_town, lc.full_name landlord_name, lc.address landlord_address, lc.town landlord_town,
                p.title property_title, p.ref property_ref, p.address property_address, p.area property_area, p.state property_state,
                gc.full_name guarantor_name, gc.address guarantor_address, gc.occupation guarantor_occupation
            FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN customers lc ON lc.id = t.landlord_id JOIN properties p ON p.id = t.property_id
            LEFT JOIN customers gc ON gc.id = t.guarantor_id WHERE t.id = ? AND t.deleted_at IS NULL", [$id]);
        if (!$t) http_error(404, 'That tenancy could not be found.');
        return $t;
    }

    public function actionCreate(): void
    {
        $this->post();
        Auth::require('agreements.generate');
        $t = $this->tenancy($this->int('tenancy_id', 'POST'));
        if ($t['status'] === 'terminated') { flash('danger', 'This tenancy has been terminated. An agreement cannot be generated for it.'); redirect('tenancies/view', ['id' => $t['id']]); }
        $tpl = db_row('SELECT * FROM agreement_templates WHERE id = ? AND is_active = 1', [$this->int('template_id', 'POST')]);
        if (!$tpl) { flash('danger', 'Choose a template.'); redirect('agreements/generate', ['tenancy' => $t['id']]); }
        $v = new Validator($_POST);
        $purpose = $v->text('use_purpose', 'Permitted use', true, 120);
        $extra = $v->area('extra_terms', 'Additional terms', false, 4000);
        if ($v->errors) { flash('danger', implode(' ', $v->errors)); redirect('agreements/generate', ['tenancy' => $t['id']]); }

        $blank = '______________________';
        $addr = fn($a, $town) => trim((string)$a . ($town ? ', ' . $town : '')) ?: $blank;
        $propAddr = trim($t['property_address'] . ', ' . $t['property_area'] . ', ' . $t['property_state']);
        $guar = '';
        if ($t['guarantor_id']) {
            $clause = Agreement::fill(setting('agreement_guarantor_clause'), [
                'guarantor_name' => '<strong>' . e($t['guarantor_name']) . '</strong>', 'guarantor_address' => e($t['guarantor_address'] ?: $blank),
                'guarantor_occupation' => $t['guarantor_occupation'] ? ', ' . e($t['guarantor_occupation']) : '']);
            $guar = '<h3>GUARANTOR</h3><p>' . $clause . '</p>';
        }
        $now = date('Y-m-d');
        db()->beginTransaction();
        try {
            $year = date('Y');
            $ref = setting('agreement_prefix', 'TEA-AGR-') . $year . '-' . str_pad((string)next_sequence('agreement_' . $year), 5, '0', STR_PAD_LEFT);
            $vars = [
                'agreement_no' => e($ref), 'tenancy_ref' => e($t['ref']), 'date_long' => e(Agreement::longDate($now)),
                'landlord_name' => e($t['landlord_name']), 'landlord_address' => e($addr($t['landlord_address'], $t['landlord_town'])),
                'tenant_name' => e($t['tenant_name']), 'tenant_address' => e($addr($t['tenant_address'], $t['tenant_town'])),
                'property_title' => e($t['property_title']), 'property_address' => e($propAddr), 'property_ref' => e($t['property_ref']),
                'rent_amount' => e(money($t['rent_amount'])), 'rent_words' => e(Money::words($t['rent_amount'])), 'rent_frequency_text' => e(Tenancy::FREQ_TEXT[$t['rent_frequency']]),
                'duration_text' => e(Agreement::durationText((int)$t['duration_months'])), 'start_date_long' => e(Agreement::longDate($t['start_date'])), 'end_date_long' => e(Agreement::longDate($t['end_date'])),
                'start_date' => e(fmt_date($t['start_date'])), 'end_date' => e(fmt_date($t['end_date'])), 'purpose' => e($purpose),
                'security_deposit' => e(money($t['security_deposit'])), 'total_payable' => e(money($t['total_payable'])), 'company_name' => e(setting('company_name')),
                'extra_terms' => $extra ? '<h3>ADDITIONAL TERMS</h3><p>' . nl2br(e($extra)) . '</p>' : '',
                'guarantor_block' => $guar, 'signature_block' => Agreement::signatureBlock($t['guarantor_name'], $t['landlord_name'], $t['tenant_name']),
            ];
            $body = Agreement::fill($tpl['body_html'], $vars);
            db_exec("UPDATE agreements SET status = 'superseded' WHERE tenancy_id = ? AND status = 'current'", [(int)$t['id']]);
            db_run('INSERT INTO agreements (ref, tenancy_id, template_id, template_name, body_html, use_purpose, extra_terms, generated_by) VALUES (?,?,?,?,?,?,?,?)',
                [$ref, (int)$t['id'], (int)$tpl['id'], $tpl['name'], $body, $purpose, $extra, Auth::id()]);
            $id = db_id();
            Audit::log('create', 'agreements', $id, "Generated agreement $ref for tenancy {$t['ref']} from template \"{$tpl['name']}\"");
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', "Agreement $ref generated and stored against the tenancy.");
        redirect('agreements/view', ['id' => $id]);
    }

    /* ---------- view / print / pdf ---------- */
    public function actionView(): void
    {
        $a = $this->agreement($this->int('id'));
        $t = db_row('SELECT t.id, t.ref, tc.full_name tenant_name FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id WHERE t.id = ?', [(int)$a['tenancy_id']]);
        $this->view('agreements/view', ['a' => $a, 't' => $t, 'pageTitle' => $a['ref'], 'active' => 'tenancies']);
    }

    private function page(array $a, bool $pdf, bool $auto = false): string
    {
        $css = Agreement::css() . '@page{size:A4;margin:16mm 16mm 20mm}' . ($pdf ? '' : 'body{padding:18px 26px;max-width:860px;margin:0 auto;background:#fff}');
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . e($a['ref']) . '</title><style>' . $css . '</style></head><body>' . Agreement::document($a, $pdf)
            . ($auto ? '<script src="' . e(asset('js/print.js')) . '"></script>' : '') . '</body></html>';
    }
    private function printed(array $a, string $how): void
    {
        db_exec('UPDATE agreements SET print_count = print_count + 1 WHERE id = ?', [(int)$a['id']]);
        Audit::log($a['print_count'] > 0 ? 'reprint' : 'print', 'agreements', $a['id'], ($a['print_count'] > 0 ? 'Reprinted' : 'Printed') . " agreement {$a['ref']} ($how)");
    }

    public function actionRaw(): void
    {
        $a = $this->agreement($this->int('id'));
        $auto = $this->int('print') === 1;
        if ($auto) $this->printed($a, 'print');
        header('Content-Type: text/html; charset=utf-8');
        echo $this->page($a, false, $auto);
        exit;
    }

    public function actionPdf(): void
    {
        $a = $this->agreement($this->int('id'));
        try {
            $pdf = Pdf::render($this->page($a, true), 'A4', 'portrait', true);
        } catch (Throwable $e) {
            app_log('Agreement PDF failed: ' . $e->getMessage());
            http_error(500, 'The PDF could not be created. You can use Print instead.');
        }
        $this->printed($a, 'PDF');
        Pdf::send($pdf, 'Agreement-' . $a['ref'] . '.pdf', $this->int('inline') === 1);
    }

    /* ---------- templates ---------- */
    public function actionTemplates(): void
    {
        Auth::require('templates.manage');
        $rows = db_all('SELECT t.*, u.full_name editor, (SELECT COUNT(*) FROM agreements a WHERE a.template_id = t.id) used FROM agreement_templates t LEFT JOIN users u ON u.id = t.updated_by ORDER BY t.is_default DESC, t.name');
        $this->view('agreements/templates', ['rows' => $rows, 'pageTitle' => 'Agreement templates', 'active' => 'templates']);
    }

    public function actionTemplateEdit(): void
    {
        Auth::require('templates.manage');
        $id = $this->int('id');
        $tpl = $id ? db_row('SELECT * FROM agreement_templates WHERE id = ?', [$id]) : ['id' => 0, 'name' => '', 'body_html' => (string)file_get_contents(TEAMS_ROOT . '/config/default_agreement.html'), 'is_default' => 0, 'is_active' => 1];
        if (!$tpl) http_error(404, 'That template could not be found.');
        form_set($tpl, []);
        $this->view('agreements/template_form', ['tpl' => $tpl, 'placeholders' => Agreement::placeholders(), 'pageTitle' => $id ? 'Edit template' : 'New template', 'active' => 'templates']);
    }

    public function actionTemplateSave(): void
    {
        $this->post();
        Auth::require('templates.manage');
        $id = $this->int('id', 'POST');
        $v = new Validator($_POST);
        $name = $v->text('name', 'Template name', true, 120, 3);
        $raw = (string)($_POST['body_html'] ?? '');
        if (mb_strlen(trim($raw)) < 50) $v->fail('body_html', 'The template text is too short.');
        $body = Agreement::sanitize($raw);
        if ($body === '' && !isset($v->errors['body_html'])) $v->fail('body_html', 'The template has no usable content.');
        if ($v->errors) {
            form_set($_POST, $v->errors);
            $this->view('agreements/template_form', ['tpl' => ['id' => $id, 'name' => $name], 'placeholders' => Agreement::placeholders(), 'pageTitle' => 'Edit template', 'active' => 'templates']);
            return;
        }
        $default = empty($_POST['is_default']) ? 0 : 1;
        $activeFlag = empty($_POST['is_active']) ? 0 : 1;
        if ($default) $activeFlag = 1;
        db()->beginTransaction();
        try {
            if ($default) db_exec('UPDATE agreement_templates SET is_default = 0');
            if ($id) {
                if (!db_val('SELECT COUNT(*) FROM agreement_templates WHERE id = ?', [$id])) { db()->rollBack(); http_error(404, 'That template could not be found.'); }
                db_exec('UPDATE agreement_templates SET name = ?, body_html = ?, is_default = ?, is_active = ?, updated_by = ? WHERE id = ?', [$name, $body, $default, $activeFlag, Auth::id(), $id]);
                Audit::log('update', 'templates', $id, "Edited agreement template \"$name\"");
            } else {
                db_exec('INSERT INTO agreement_templates (name, body_html, is_default, is_active, updated_by) VALUES (?,?,?,?,?)', [$name, $body, $default, $activeFlag, Auth::id()]);
                $id = db_id();
                Audit::log('create', 'templates', $id, "Created agreement template \"$name\"");
            }
            if (!(int)db_val('SELECT COUNT(*) FROM agreement_templates WHERE is_default = 1')) db_exec('UPDATE agreement_templates SET is_default = 1, is_active = 1 WHERE id = ?', [$id]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        $unknown = Agreement::unknownPlaceholders($body);
        flash('success', 'Template saved. Agreements already generated are not changed.');
        if ($unknown) flash('warning', 'These placeholders are not recognised and will print as blanks: {{' . implode('}}, {{', $unknown) . '}}');
        redirect('agreements/templates');
    }

    public function actionTemplatePreview(): void
    {
        Auth::require('templates.manage');
        $tpl = db_row('SELECT * FROM agreement_templates WHERE id = ?', [$this->int('id')]);
        if (!$tpl) http_error(404, 'That template could not be found.');
        $sample = [];
        foreach (Agreement::placeholders() as $k => $_) $sample[$k] = '<span style="background:#fff3cd">[' . e($k) . ']</span>';
        $sample['signature_block'] = Agreement::signatureBlock('Sample Guarantor', 'Sample Landlord', 'Sample Tenant');
        $a = ['body_html' => Agreement::fill($tpl['body_html'], $sample), 'generated_at' => date('Y-m-d H:i:s'), 'ref' => 'PREVIEW'];
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><style>' . Agreement::css() . 'body{padding:18px 26px;max-width:860px;margin:0 auto}</style></head><body>' . Agreement::document($a, false) . '</body></html>';
        exit;
    }
}
