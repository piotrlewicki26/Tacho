<?php
declare(strict_types=1);

namespace LicenseGenerator;

/**
 * Session-based authentication manager.
 */
class Auth
{
    private const SESSION_KEY = 'licgen_user';

    public function __construct(private Database $db) {}

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Attempt to log in. Returns true on success, false on failure.
     */
    public function login(string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT id, password_hash FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->db->prepare(
            "UPDATE users SET last_login = strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime') WHERE id = ?"
        )->execute([$user['id']]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::SESSION_KEY] = [
            'id'       => $user['id'],
            'username' => $username,
        ];

        return true;
    }

    /**
     * Destroy the session.
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Returns true if a user is currently authenticated.
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * Redirect to login if the user is not authenticated.
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('/login');
        }
    }

    /**
     * Return the current user's ID, or null.
     */
    public function userId(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]['id'])
            ? (int)$_SESSION[self::SESSION_KEY]['id']
            : null;
    }

    /**
     * Return the current user's username, or null.
     */
    public function username(): ?string
    {
        return $_SESSION[self::SESSION_KEY]['username'] ?? null;
    }

    // -----------------------------------------------------------------------
    // User management (used by setup.php)
    // -----------------------------------------------------------------------

    /**
     * Create the initial admin account. Throws if username already exists.
     */
    public function createUser(string $username, string $password): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)'
        );
        $stmt->execute([$username, $hash]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Change password for an existing user.
     */
    public function changePassword(int $userId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                 ->execute([$hash, $userId]);
    }

    /**
     * Returns the number of existing users.
     */
    public function countUsers(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) AS c FROM users')->fetchColumn();
    }
}
