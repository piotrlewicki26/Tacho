<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\Database;
use Models\User;

class AuthController
{
    public function showLogin(array $params): void
    {
        if (Auth::check()) { header('Location: /'); exit; }
        $flash = Auth::getFlash();
        require __DIR__ . '/../Views/auth/login.php';
    }

    public function login(array $params): void
    {
        if (!Auth::validateCsrf()) {
            Auth::setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
            header('Location: /login'); exit;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login($email, $password)) {
            header('Location: /'); exit;
        }

        Auth::setFlash('error', 'Nieprawidłowy e-mail lub hasło.');
        header('Location: /login'); exit;
    }

    public function logout(array $params): void
    {
        Auth::logout();
        header('Location: /login'); exit;
    }

    public function showRegister(array $params): void
    {
        Auth::requireRole('superadmin', 'admin');
        $flash     = Auth::getFlash();
        $companies = (new \Models\Company())->all();
        require __DIR__ . '/../Views/auth/register.php';
    }

    public function register(array $params): void
    {
        Auth::requireRole('superadmin', 'admin');
        if (!Auth::validateCsrf()) {
            Auth::setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
            header('Location: /admin/users/create'); exit;
        }

        $data = [
            'name'       => trim($_POST['name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'password'   => $_POST['password'] ?? '',
            'role'       => $_POST['role'] ?? 'operator',
            'company_id' => (int)($_POST['company_id'] ?? 0) ?: null,
        ];

        $errors = [];
        if (empty($data['name']))     $errors[] = 'Imię i nazwisko jest wymagane.';
        if (empty($data['email']))    $errors[] = 'E-mail jest wymagany.';
        if (strlen($data['password']) < 8) $errors[] = 'Hasło musi mieć min. 8 znaków.';

        if ($errors) {
            Auth::setFlash('error', implode(' ', $errors));
            header('Location: /admin/users/create'); exit;
        }

        // Enforce operator limit per license (for non-superadmin roles)
        $targetCompanyId = $data['company_id'] ?? Auth::companyId();
        if (
            $targetCompanyId &&
            in_array($data['role'], ['admin', 'operator'], true) &&
            !\Core\License::checkOperatorLimit($targetCompanyId)
        ) {
            Auth::setFlash('error', 'Limit operatorów/administratorów wyczerpany. Zaktualizuj licencję.');
            header('Location: /admin/users/create'); exit;
        }

        (new User())->create($data);
        Auth::log('user_created', 'Utworzono użytkownika: ' . $data['email']);
        Auth::setFlash('success', 'Użytkownik utworzony pomyślnie.');
        header('Location: /admin/users'); exit;
    }
}
