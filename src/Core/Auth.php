<?php
declare(strict_types=1);
namespace Core;

/**
 * Session-based authentication helper.
 */
class Auth
{
    private const SESSION_KEY = 'tacho_user';
    private const FLASH_KEY   = 'tacho_flash';

    // ── Login / Logout ─────────────────────────────────────────────────────

    public static function login(string $email, string $password): bool
    {
        $user = Database::fetchOne(
            'SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1',
            ['email' => $email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Regenerate session ID on login
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'         => (int) $user['id'],
            'company_id' => $user['company_id'] ? (int) $user['company_id'] : null,
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
        ];

        // Update last login
        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

        self::log('login', 'Logowanie użytkownika');
        return true;
    }

    public static function logout(): void
    {
        self::log('logout', 'Wylogowanie użytkownika');
        $_SESSION[self::SESSION_KEY] = null;
        session_destroy();
    }

    // ── Session Accessors ──────────────────────────────────────────────────

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /** @return array|null */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]['id'])
            ? (int) $_SESSION[self::SESSION_KEY]['id']
            : null;
    }

    public static function companyId(): ?int
    {
        return $_SESSION[self::SESSION_KEY]['company_id'] ?? null;
    }

    public static function role(): string
    {
        return $_SESSION[self::SESSION_KEY]['role'] ?? 'operator';
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    // Superadmin sees everything; other roles are scoped to company
    public static function isSuperAdmin(): bool
    {
        return self::role() === 'superadmin';
    }

    // ── Superadmin company context (view-as) ───────────────────────────────

    /**
     * For superadmin: set the company they are currently viewing as.
     * Has no effect for non-superadmin users.
     */
    public static function setViewedCompany(int $companyId): void
    {
        if (self::isSuperAdmin()) {
            $_SESSION['tacho_viewed_company'] = $companyId;
        }
    }

    /** Clear the superadmin's viewed-company context (back to global view). */
    public static function clearViewedCompany(): void
    {
        unset($_SESSION['tacho_viewed_company']);
    }

    /**
     * Return the company ID that a superadmin is currently "viewing as",
     * or null if no company is selected (global view).
     * Always returns null for non-superadmin (they use companyId() instead).
     */
    public static function viewedCompanyId(): ?int
    {
        if (!self::isSuperAdmin()) return null;
        return isset($_SESSION['tacho_viewed_company'])
            ? (int) $_SESSION['tacho_viewed_company']
            : null;
    }

    /**
     * Effective company ID for scoping data queries:
     * – Superadmin with a company selected → that company
     * – Superadmin with no company selected → null (global totals)
     * – Normal user → their own company_id
     */
    public static function effectiveCompanyId(): ?int
    {
        if (self::isSuperAdmin()) {
            return self::viewedCompanyId();
        }
        return self::companyId();
    }

    // ── Guards ─────────────────────────────────────────────────────────────

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * For non-superadmin users: require an active license on their company.
     * Redirect to /license-required if none found.
     * Safe to call on every authenticated page.
     */
    public static function requireActiveLicense(): void
    {
        if (self::isSuperAdmin()) return;
        $cid = self::companyId();
        if (!$cid) return; // no company assigned – separate guard handles this
        if (!\Core\License::getActive($cid)) {
            $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            // Allow the license-required page itself to render without redirect loop
            if ($current === '/license-required') return;
            header('Location: /license-required');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            exit('403 Forbidden');
        }
    }

    // ── Flash Messages ─────────────────────────────────────────────────────

    public static function setFlash(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY] = ['type' => $type, 'message' => $message];
    }

    /** Returns flash once and clears it. */
    public static function getFlash(): ?array
    {
        $flash = $_SESSION[self::FLASH_KEY] ?? null;
        unset($_SESSION[self::FLASH_KEY]);
        return $flash;
    }

    // ── Audit Log ──────────────────────────────────────────────────────────

    public static function log(string $action, string $details = ''): void
    {
        try {
            Database::insert('audit_log', [
                'user_id'    => self::id(),
                'company_id' => self::companyId(),
                'action'     => $action,
                'details'    => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Audit log should not break the app
        }
    }

    // ── CSRF ───────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
