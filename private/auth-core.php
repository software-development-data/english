<?php
declare(strict_types=1);

function reply(int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail(int $status, string $message, string $code = 'REQUEST_FAILED'): never {
    reply($status, ['ok' => false, 'code' => $code, 'message' => $message]);
}
function field(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) || is_int($value) || is_float($value) ? trim((string)$value) : '';
}
function email_value(string $value): string {
    $email = strtolower(trim($value));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Địa chỉ email chưa hợp lệ.', 'EMAIL_INVALID');
    return $email;
}
function password_value(string $value): string {
    if (strlen($value) < 8 || strlen($value) > 1024 || str_contains($value, "\0")) fail(422, 'Mật khẩu cần có ít nhất 8 ký tự.', 'PASSWORD_INVALID');
    return $value;
}
function csrf(): string {
    if (empty($_SESSION['eq_csrf']) || !is_string($_SESSION['eq_csrf'])) $_SESSION['eq_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['eq_csrf'];
}
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['eq_csrf'] ?? '';
    if (!is_string($sent) || !is_string($known) || $sent === '' || !hash_equals($known, $sent)) fail(403, 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.', 'CSRF_INVALID');
}
function limit_attempts(PDO $pdo, string $bucket, int $maximum, int $seconds): void {
    $now = time(); $key = hash('sha256', $bucket); $until = $now + max(1, $seconds);
    $q = $pdo->prepare('INSERT INTO eq_rate_limits(bucket,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires_at<=?,1,hits+1),expires_at=IF(expires_at<=?,?,expires_at)');
    $q->execute([$key, $until, $now, $now, $until]);
    $q = $pdo->prepare('SELECT hits FROM eq_rate_limits WHERE bucket=?'); $q->execute([$key]);
    if ((int)$q->fetchColumn() > $maximum) fail(429, 'Bạn thao tác quá nhanh. Hãy chờ một chút rồi thử lại.', 'RATE_LIMITED');
}
function current_account(PDO $pdo): ?array {
    $uid = $_SESSION['eq_user_id'] ?? null;
    if (!is_int($uid) || $uid < 1) return null;
    $user = hub_q($pdo, 'SELECT id,email,display_name,auth_version FROM eq_users WHERE id=?', [$uid])->fetch(PDO::FETCH_ASSOC);
    if (!$user || (int)$user['auth_version'] !== (int)($_SESSION['eq_auth_version'] ?? 0) || !eq_auth_access($pdo, $user)) { $_SESSION = []; return null; }
    return $user;
}
function session_payload(?array $user = null, ?string $message = null): never {
    $out = ['ok' => true, 'user' => $user ? ['id' => (int)$user['id'], 'email' => (string)$user['email'], 'display_name' => (string)$user['display_name']] : null, 'csrf' => csrf()];
    if ($message !== null && $message !== '') $out['message'] = $message;
    reply(200, $out);
}
function ok_session(?array $user = null, string $message = ''): never { session_payload($user, $message); }
