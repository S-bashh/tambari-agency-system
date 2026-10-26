<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    public function actionLogin(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
        $error = null;
        $login = '';
        if (is_post()) {
            Csrf::verify();
            $login = $this->str('username', 'POST');
            $res = Auth::attempt($login, (string)($_POST['password'] ?? ''));
            if ($res['ok']) {
                redirect('dashboard');
            }
            $error = $res['error'];
        }
        $this->view('auth/login', ['error' => $error, 'login' => $login, 'pageTitle' => 'Sign in'], 'auth');
    }

    public function actionLogout(): void
    {
        $this->post();
        Auth::logout();
        Auth::startSession();
        flash('success', 'You have been signed out.');
        redirect('auth/login');
    }
}
