<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/lib/auth.php';

start_session();

$pdo = db();

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
// auth.php の require_login() が付ける to= を尊重（他ページから飛んできた時）
$to = isset($_GET['to']) ? (string)$_GET['to'] : null;

// 画面表示用
$error = '';
$step = 'input'; // input | confirm
$libeProfileUrl = '';
$libeName = '';
$libeIconUrl = '';

/**
 * リベ（libe）プロフィールページから、名前とアイコンをできるだけ堅牢に抽出
 * - OGP（og:image / og:title）優先
 * - なければ <title> などから推測
 */
function fetch_libe_profile(string $libeProfileUrl): array
{
    $libeProfileUrl = trim($libeProfileUrl);

    // 軽いバリデーション（URL形式＆想定ドメイン）
    if (!filter_var($libeProfileUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('invalid_url');
    }

    // ここは運用に合わせて許可範囲を調整OK（libecity.jp / libecity.com 想定）
    $host = (string)parse_url($libeProfileUrl, PHP_URL_HOST);
    if ($host === '' || !preg_match('/(^|\.)libecity\.(jp|com)$/', $host)) {
        throw new RuntimeException('invalid_host');
    }

    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'header'  => implode("\r\n", [
                'User-Agent: Mozilla/5.0 (compatible; OffkaiTagApp/1.0)',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]),
        ],
    ]);

    $html = @file_get_contents($libeProfileUrl, false, $context);
    if ($html === false || trim($html) === '') {
        throw new RuntimeException('fetch_failed');
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $getMeta = function(string $attr, string $value): string {
        return '';
    };

    // OGP抽出
    $ogImage = '';
    $ogTitle = '';

    $nodes = $xpath->query("//meta[@property='og:image']/@content");
    if ($nodes && $nodes->length > 0) $ogImage = trim((string)$nodes->item(0)->nodeValue);

    $nodes = $xpath->query("//meta[@property='og:title']/@content");
    if ($nodes && $nodes->length > 0) $ogTitle = trim((string)$nodes->item(0)->nodeValue);

    // title fallback
    $titleText = '';
    $nodes = $xpath->query("//title");
    if ($nodes && $nodes->length > 0) $titleText = trim((string)$nodes->item(0)->textContent);

    // 名前っぽいものを決定（og:title > title）
    $nameCandidate = $ogTitle !== '' ? $ogTitle : $titleText;

    // 「〜 | 〜」とか「〜 - 〜」をよく使うので先頭を採用
    $libeName = preg_split('/\s*[\|\-]\s*/u', $nameCandidate)[0] ?? '';
    $libeName = trim($libeName);

    // アイコンは og:image を優先（取れなかった場合は空のまま）
    $libeIconUrl = trim($ogImage);

    if ($libeName === '' && $libeIconUrl === '') {
        throw new RuntimeException('parse_failed');
    }

    return [
        'libe_name' => $libeName,
        'libe_icon_url' => $libeIconUrl,
        'libe_profile_url' => $libeProfileUrl,
    ];
}

/** users から profile_url で検索（見つかれば保存済みの libe_name を優先） */
function find_user_by_profile_url(PDO $pdo, string $libeProfileUrl): ?array
{
    $stmt = $pdo->prepare("SELECT user_id, profile_url, libe_name, user_icon_url FROM users WHERE profile_url = :u LIMIT 1");
    $stmt->execute([':u' => $libeProfileUrl]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** users を upsert（profile_url 一意前提） */
function upsert_user(PDO $pdo, string $libeProfileUrl, string $libeName, string $libeIconUrl): int
{
    // 既存チェック
    $existing = find_user_by_profile_url($pdo, $libeProfileUrl);
    $now = date('Y-m-d H:i:s');

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE users
               SET libe_name = :n,
                   user_icon_url = :i,
                   updated_at = :t
             WHERE user_id = :id
        ");
        $stmt->execute([
            ':n' => $libeName,
            ':i' => $libeIconUrl,
            ':t' => $now,
            ':id' => (int)$existing['user_id'],
        ]);
        return (int)$existing['user_id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (profile_url, libe_name, user_icon_url, created_at, updated_at)
        VALUES (:u, :n, :i, :c, :t)
    ");
    $stmt->execute([
        ':u' => $libeProfileUrl,
        ':n' => $libeName,
        ':i' => $libeIconUrl,
        ':c' => $now,
        ':t' => $now,
    ]);
    return (int)$pdo->lastInsertId();
}

// --- POST処理（読み込み or ログイン） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    // hiddenで引き回す
    $eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : $eventId;
    $to = isset($_POST['to']) && $_POST['to'] !== '' ? (string)$_POST['to'] : $to;

    if ($action === 'load') {
        $libeProfileUrl = trim((string)($_POST['libe_profile_url'] ?? ''));

        if ($libeProfileUrl === '') {
            $error = 'プロフィールURLを入力してください。';
            $step = 'input';
        } else {
            try {
                $data = fetch_libe_profile($libeProfileUrl);
                $libeProfileUrl = $data['libe_profile_url'];
                $libeIconUrl = $data['libe_icon_url'];
                $libeName = $data['libe_name'];

                // 仕様：URLでユーザーT検索→あれば保存済みリベネームを使用
                $existing = find_user_by_profile_url($pdo, $libeProfileUrl);
                if ($existing && !empty($existing['libe_name'])) {
                    $libeName = (string)$existing['libe_name'];
                }

                $step = 'confirm';
            } catch (Throwable $e) {
                $error = 'アカウントが読み込めません。';
                $step = 'input';
            }
