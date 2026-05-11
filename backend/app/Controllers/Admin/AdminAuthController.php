<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\SessionService;

class AdminAuthController {
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly SessionService $session = new SessionService(),
        private readonly CsrfService $csrf = new CsrfService(),
        private readonly Validator $validator = new Validator(),
    ) {}

    public function showLogin(Request $request): void {
        $this->session->start();

        $csrfToken = $this->csrf->token();
        Response::view($this->renderLoginForm($csrfToken));
    }

    public function login(Request $request): void {
        $this->session->start();

        $input = $this->validator->validate($request->input(), [
            'email' => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        $authResult = $this->auth->login((string)$input['email'], (string)$input['password']);
        if ($authResult === null || (($authResult['user']['role'] ?? '') !== 'admin')) {
            Response::view($this->renderLoginForm($this->csrf->token(), 'Invalid credentials'), 401);
            return;
        }

        $this->session->regenerateId();
        $this->session->setAdminUser($authResult['user']);
        header('Location: /admin/dashboard', true, 302);
    }

    public function logout(Request $request): void {
        $this->session->clear();
        header('Location: /admin/login', true, 302);
    }

    private function renderLoginForm(string $csrfToken, ?string $error = null): string {
        $errorHtml = $error === null ? '' : '<p style="color: #b00020;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';

        return <<<HTML
<h1>Admin Login</h1>
{$errorHtml}
<form method="POST" action="/admin/login">
  <input type="hidden" name="_csrf" value="{$csrfToken}">
  <label>Email <input type="email" name="email" required></label><br><br>
  <label>Password <input type="password" name="password" required></label><br><br>
  <button type="submit">Login</button>
</form>
HTML;
    }
}
