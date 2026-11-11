<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('eqpro_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$private = dirname(__DIR__, 2) . '/private';
require_once $private . '/auth-core.php';
try {
    $configFile = $private . '/config.php';
    if (!is_file($configFile)) throw new RuntimeException('CONFIG_MISSING');
    $config = require $configFile;
    if (!is_array($config) || !isset($config['db']['host'], $config['db']['name'], $config['db']['user'], $config['db']['password'])) throw new RuntimeException('CONFIG_INVALID');
    $db = $config['db'];
    $dsn = $db['dsn'] ?? ('mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4');
    $pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    require_once $private . '/hub-common.php';
    require_once $private . '/auth-workflow.php';
    require_once $private . '/game.php';
    require_once $private . '/profile.php';
} catch (Throwable $e) {
    error_log('English Quest auth bootstrap failed: ' . get_class($e));
    $isConfig = str_contains($e->getMessage(), 'CONFIG_');
    reply(503, ['ok' => false, 'code' => $isConfig ? 'CONFIG_NOT_READY' : 'DATABASE_NOT_READY', 'message' => $isConfig ? 'Chưa tìm thấy cấu hình riêng tư. Đặt private/config.php ngang hàng với public_html.' : 'Máy chủ chưa kết nối được cơ sở dữ liệu. Kiểm tra private/config.php và nhập database/ENGLISH_QUEST_COMPLETE.sql.']);
}

$action = (string)($_GET['action'] ?? '');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 65536) fail(413, 'Yêu cầu vượt quá dung lượng cho phép.', 'REQUEST_TOO_LARGE');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($decoded)) fail(400, 'Dữ liệu gửi lên chưa hợp lệ.', 'JSON_INVALID');
    $input = $decoded;
    require_csrf();
} elseif (!in_array($action, ['session', 'captcha', 'health', 'game_catalog', 'game_progress'], true)) {
    fail(405, 'Phương thức yêu cầu chưa được hỗ trợ.', 'METHOD_NOT_ALLOWED');
}

try {
    if ($action === 'health') { $pdo->query('SELECT 1'); reply(200, ['ok' => true, 'service' => 'english-quest-auth']); }
    if ($action === 'session') session_payload(current_account($pdo));
    if ($action === 'captcha') reply(200, ['ok' => true] + eq_captcha() + ['csrf' => csrf()]);

    if ($action === 'login') {
        $email = email_value(field($input, 'email')); $password = field($input, 'password');
        limit_attempts($pdo, 'login-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 900);
        limit_attempts($pdo, 'login-email:' . $email, 12, 900);
        $user = hub_q($pdo, 'SELECT id,email,password_hash,display_name,auth_version FROM eq_users WHERE email=?', [$email])->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            if ($user) eq_auth_log($pdo, 'login_failed', (int)$user['id'], $email);
            fail(401, 'Email hoặc mật khẩu chưa chính xác.', 'LOGIN_INVALID');
        }
        $state = eq_auth_state($pdo, (int)$user['id']);
        if (!$state || !eq_auth_access($pdo, $user)) {
            eq_auth_log($pdo, 'login_unverified', (int)$user['id'], $email);
            fail(403, 'Email chưa được xác minh. Hãy gửi lại liên kết xác minh.', 'EMAIL_UNVERIFIED');
        }
        session_regenerate_id(true);
        $_SESSION['eq_user_id'] = (int)$user['id'];
        $_SESSION['eq_auth_version'] = (int)$user['auth_version'];
        $_SESSION['eq_csrf'] = bin2hex(random_bytes(32));
        eq_auth_log($pdo, 'login_success', (int)$user['id'], $email);
        session_payload($user, 'Đăng nhập thành công.');
    }

    if (in_array($action, ['register', 'verify', 'resend_verify', 'forgot', 'reset'], true)) eq_auth_handle($pdo, $config, $action, $input);

    if ($action === 'logout') {
        $user = current_account($pdo);
        if ($user) {
            try { hub_q($pdo, 'UPDATE eqx_sessions SET revoked=1 WHERE token=?', [hash('sha256', session_id())]); } catch (PDOException $e) { }
            eq_auth_log($pdo, 'logout', (int)$user['id'], (string)$user['email']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) { $cp = session_get_cookie_params(); setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $cp['path'], 'domain' => $cp['domain'], 'secure' => $cp['secure'], 'httponly' => $cp['httponly'], 'samesite' => $cp['samesite'] ?? 'Lax']); }
        session_destroy();
        session_start();
        session_payload(null, 'Bạn đã đăng xuất.');
    }

    if ($action === 'update') {
        $user = current_account($pdo); if (!$user) fail(401, 'Vui lòng đăng nhập lại.', 'AUTH_REQUIRED');
        $name = trim(field($input, 'display_name'));
        $nameLength = preg_match_all('/./us', $name);
        if ($name === '' || $nameLength === false || $nameLength < 1 || $nameLength > 60 || preg_match('/[\\x00-\\x1f\\x7f]/u', $name)) fail(422, 'Tên hiển thị cần từ 1 đến 60 ký tự.', 'NAME_INVALID');
        $newEmail = email_value(field($input, 'email'));
        $currentPassword = field($input, 'current_password');
        $newPassword = field($input, 'new_password');
        if ($newEmail !== $user['email']) {
            if (!password_verify($currentPassword, (string)hub_q($pdo, 'SELECT password_hash FROM eq_users WHERE id=?', [$user['id']])->fetchColumn())) fail(403, 'Mật khẩu hiện tại chưa chính xác.', 'PASSWORD_INVALID');
            eqp_email_begin($pdo, $config, $user, $input);
        }
        if ($newPassword !== '') {
            if (!password_verify($currentPassword, (string)hub_q($pdo, 'SELECT password_hash FROM eq_users WHERE id=?', [$user['id']])->fetchColumn())) fail(403, 'Mật khẩu hiện tại chưa chính xác.', 'PASSWORD_INVALID');
            $newPassword = password_value($newPassword);
            hub_q($pdo, 'UPDATE eq_users SET display_name=?,password_hash=?,auth_version=auth_version+1 WHERE id=?', [$name, password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            $_SESSION['eq_auth_version']++;
        } else hub_q($pdo, 'UPDATE eq_users SET display_name=? WHERE id=?', [$name, $user['id']]);
        $user['display_name'] = $name;
        eq_auth_log($pdo, 'account_updated', (int)$user['id'], (string)$user['email']);
        session_payload($user, 'Đã lưu thay đổi tài khoản.');
    }

    if ($action === 'email_change_confirm') eqp_email_confirm($pdo, $input);
    if (str_starts_with($action, 'game_')) handle_game($pdo, $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', $action, $input);
    fail(404, 'Không tìm thấy chức năng tài khoản.', 'ACTION_NOT_FOUND');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('English Quest auth request failed: ' . $action . ' ' . get_class($e));
    $msg = strtolower($e->getMessage());
    if ($e instanceof PDOException && (str_contains($msg, 'doesn\'t exist') || str_contains($msg, 'no such table') || str_contains($msg, 'unknown column'))) {
        reply(503, ['ok' => false, 'code' => 'DATABASE_MIGRATION_REQUIRED', 'message' => 'Cơ sở dữ liệu chưa cài đủ bảng. Nhập database/ENGLISH_QUEST_COMPLETE.sql rồi thử lại.']);
    }
    reply(500, ['ok' => false, 'code' => 'SERVER_ERROR', 'message' => 'Máy chủ gặp lỗi khi xử lý yêu cầu. Kiểm tra error log trong Hostinger để biết chi tiết.']);
}
