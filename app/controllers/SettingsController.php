<?php
declare(strict_types=1);

final class SettingsController extends Controller
{
    private const KEYS = ['company_name', 'company_tagline', 'company_address', 'company_phone', 'company_email', 'company_website', 'company_reg',
        'currency_symbol', 'currency_code', 'date_format', 'page_size', 'session_timeout', 'prefix_customer', 'prefix_property',
        'prefix_tenancy', 'receipt_prefix', 'agreement_prefix', 'expiry_alert_days', 'receipt_title', 'receipt_footer', 'agreement_footer', 'legal_disclaimer', 'agreement_guarantor_clause', 'prefix_sale', 'prefix_plot', 'backup_reminder_days'];

    public function actionIndex(): void
    {
        Auth::require('settings.manage');
        $vals = [];
        foreach (self::KEYS as $k) $vals[$k] = setting($k);
        form_set($vals, []);
        $this->view('settings/index', ['pageTitle' => 'Settings', 'active' => 'settings']);
    }

    public function actionSave(): void
    {
        $this->post();
        Auth::require('settings.manage');
        $v = new Validator($_POST);
        $v->text('company_name', 'Company name', true, 120);
        $v->text('company_tagline', 'Tagline', false, 200);
        $v->text('company_address', 'Address', true, 255);
        $v->text('company_phone', 'Phone number(s)', true, 120);
        $v->email('company_email', 'Email');
        $v->text('company_website', 'Website', false, 150);
        $v->text('company_reg', 'Registration number', false, 60);
        $v->text('currency_symbol', 'Currency symbol', true, 4);
        $code = $v->text('currency_code', 'Currency code', true, 3, 3);
        if ($code !== null && !preg_match('/^[A-Za-z]{3}$/', $code)) $v->fail('currency_code', 'Use a 3-letter code such as NGN.');
        $v->enum('date_format', 'Date format', ['d/m/Y', 'd-m-Y', 'j M Y', 'Y-m-d'], true);
        $v->int('page_size', 'Rows per page', true, 5, 100);
        $v->int('session_timeout', 'Session timeout (minutes)', true, 5, 480);
        foreach (['prefix_customer' => 'Customer prefix', 'prefix_property' => 'Property prefix'] as $k => $l) {
            $x = $v->text($k, $l, true, 12);
            if ($x !== null && !preg_match('/^[A-Za-z0-9-]+$/', $x)) $v->fail($k, 'Use letters, numbers and dashes only.');
        }
        $v->int('expiry_alert_days', 'Expiry alert window (days)', true, 7, 365);
        foreach (['prefix_tenancy' => 'Tenancy prefix', 'receipt_prefix' => 'Receipt prefix', 'agreement_prefix' => 'Agreement prefix'] as $k => $l) {
            $x = $v->text($k, $l, true, 12);
            if ($x !== null && !preg_match('/^[A-Za-z0-9-]+$/', $x)) $v->fail($k, 'Use letters, numbers and dashes only.');
        }
        foreach (['prefix_sale' => 'Sale prefix', 'prefix_plot' => 'Plot prefix'] as $k => $l) {
            $x = $v->text($k, $l, true, 12);
            if ($x !== null && !preg_match('/^[A-Za-z0-9-]+$/', $x)) $v->fail($k, 'Use letters, numbers and dashes only.');
        }
        $v->int('backup_reminder_days', 'Backup reminder (days)', true, 1, 90);
        $v->text('receipt_title', 'Receipt title', true, 60);
        $v->area('receipt_footer', 'Receipt footer text', false, 500);
        $v->area('agreement_footer', 'Agreement footer text', false, 500);
        $v->area('legal_disclaimer', 'Legal disclaimer', false, 1500);
        $g = $v->area('agreement_guarantor_clause', 'Guarantor clause', false, 1500);
        if ($g !== null && preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $g, $mm) && array_diff($mm[1], ['guarantor_name', 'guarantor_address', 'guarantor_occupation'])) {
            $v->fail('agreement_guarantor_clause', 'Only {{guarantor_name}}, {{guarantor_address}} and {{guarantor_occupation}} can be used here.');
        }
        $logoName = null;
        try {
            $lf = Upload::files('logo');
            if ($lf) $logoName = Upload::saveLogo($lf[0]);
        } catch (UploadException $e) {
            $v->fail('logo', $e->getMessage());
        }
        if ($v->errors) {
            form_set($_POST, $v->errors);
            $this->view('settings/index', ['pageTitle' => 'Settings', 'active' => 'settings']);
            return;
        }
        $old = [];
        foreach (self::KEYS as $k) $old[$k] = setting($k);
        $new = [];
        foreach (self::KEYS as $k) $new[$k] = (string)($v->d[$k] ?? '');
        $new['company_email'] = strtolower($new['company_email']);
        $new['currency_code'] = strtoupper($new['currency_code']);
        db()->beginTransaction();
        try {
            foreach ($new as $k => $val) setting_set($k, $val);
            $ch = Audit::diff($old, $new, self::KEYS);
            if ($logoName) {
                $prev = setting('logo_file');
                setting_set('logo_file', $logoName);
                $ch['logo_file'] = [$prev, $logoName];
            }
            Audit::log('settings', 'settings', null, 'Updated settings' . ($ch ? ' (changed: ' . implode(', ', array_keys($ch)) . ')' : ''), $ch ?: null);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        if ($logoName && !empty($prev) && preg_match('/^logo_[a-f0-9]+\.png$/', $prev)) @unlink(TEAMS_ROOT . '/storage/images/' . $prev);
        flash('success', 'Settings saved.');
        redirect('settings');
    }
}
