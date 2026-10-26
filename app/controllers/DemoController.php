<?php
declare(strict_types=1);

final class DemoController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('settings.manage');
        $this->view('settings/demo', ['loaded' => Demo::loaded(), 'counts' => Demo::counts(), 'real' => (int)db_val('SELECT COUNT(*) FROM customers') - (int)(Demo::counts()['customers'] ?? 0),
            'pageTitle' => 'Demo data', 'active' => 'settings']);
    }

    public function actionLoad(): void
    {
        $this->post();
        Auth::require('settings.manage');
        if (Demo::loaded()) { flash('warning', 'Demo data is already loaded.'); redirect('demo'); }
        try { Demo::load(); } catch (Throwable $e) { app_log('Demo load failed: ' . $e->getMessage()); flash('danger', 'The demo data could not be loaded (details were written to the log).'); redirect('demo'); }
        flash('success', 'Demo data loaded. Everything is marked "(Demo)" and uses DEMO- references.');
        redirect('dashboard');
    }

    public function actionRemove(): void
    {
        $this->post();
        Auth::require('settings.manage');
        $err = Demo::remove();
        flash($err ? 'danger' : 'success', $err ?? 'All demo data was removed. Your own records were not touched.');
        redirect('demo');
    }

    public function actionReset(): void
    {
        $this->post();
        Auth::require('settings.manage');
        $err = Demo::loaded() ? Demo::remove() : null;
        if ($err) { flash('danger', $err); redirect('demo'); }
        try { Demo::load(); } catch (Throwable $e) { app_log('Demo reset failed: ' . $e->getMessage()); flash('danger', 'The demo data could not be reloaded.'); redirect('demo'); }
        flash('success', 'Demo data was reset to its original state.');
        redirect('demo');
    }
}
