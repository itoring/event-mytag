<?php
declare(strict_types=1);

/**
 * セッション＆認証 共通
 * - require_login(): 未ログインなら login.php に飛ばす
 * - current_user_id(): ログイン中のuser_idを返す
 * - is_logged_in(): ログイン中かどうか
 * - login_user(): セッションにログイン情報を保存
 * - logout_user(): セッション破棄
 */

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // セキュア寄りの設定（https運用前提。httpの場合は true だとcookie送れない）
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $isHttps,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

function require_login(?string $redirectTo = null): void
{
    if (!is_logged_in()) {
        $to = $redirectTo ?? ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?to=' . urlencode($to));
        exit;
    }
}

function current_user_id(): ?int
{
    if (!is_logged_in()) return null;
    return (int)$_SESSION['user']['user_id'];
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    return $_SESSION['user'];
}

function login_user(int $userId, string $profileUrl, string $ribeName = '', string $iconUrl = ''): void
{
    start_session();
    $_SESSION['user'] = [
        'user_id'     => $userId,
        'profile_url' => $profileUrl,
        'ribe_name'   => $ribeName,
        'icon_url'    => $iconUrl,
    ];
    session_regenerate_id(true);
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];

    // セッションクッキー削除
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    session_destroy();
}
