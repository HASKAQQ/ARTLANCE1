<?php
require_once __DIR__ . '/inc_app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $phone = trim($_POST['phone'] ?? '');

    if ($_POST['action'] === 'send_code') {
        if ($phone !== LOGIN_PHONE && $phone !== ADMIN_PHONE) {
            echo json_encode(['success' => false, 'message' => 'Разрешен вход только с номера +79930170672 (или админ номер).']);
            exit;
        }

        $code = (string)rand(10000, 99999);
        $_SESSION['verification_code'] = $code;
        $_SESSION['phone_temp'] = $phone;
        echo json_encode(['success' => true, 'code' => $code]);
        exit;
    }

    if ($_POST['action'] === 'verify_code') {
        if (($_POST['code'] ?? '') !== ($_SESSION['verification_code'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Неверный код']);
            exit;
        }

        $phone = $_SESSION['phone_temp'] ?? '';
        $user = fetch_one('SELECT * FROM users WHERE phone = ?', 's', [$phone]);
        if (!$user) {
            execute_query('INSERT INTO users (phone,name,role) VALUES (?, ?, ?)', 'sss', [$phone, 'Новый пользователь', 'client']);
            $user = fetch_one('SELECT * FROM users WHERE phone = ?', 's', [$phone]);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $redirect = $user['role'] === 'admin' ? 'admin-main.php' : 'profile-artist-edit.php';
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        exit;
    }
}
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Вход</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="css/style.css"><script src="js/main.js" defer></script></head>
<body><?php include 'header.php'; ?>
<div class="container py-5" style="max-width:520px;">
  <div class="login-card" id="phoneStep"><h1 class="login-title">Вход по номеру</h1>
    <p class="small text-muted">Тестовый номер: +79930170672</p>
    <form id="phoneForm"><input type="tel" class="form-control login-input mb-3" id="phoneInput" value="+79930170672" required>
      <button type="submit" class="btn login-btn w-100">Получить код</button></form></div>
  <div class="login-card d-none" id="codeStep"><h1 class="code-title">Введите код из консоли</h1>
    <div class="code-inputs mb-4"><input type="text" class="code-input" maxlength="1" id="code1" required><input type="text" class="code-input" maxlength="1" id="code2"><input type="text" class="code-input" maxlength="1" id="code3"><input type="text" class="code-input" maxlength="1" id="code4"><input type="text" class="code-input" maxlength="1" id="code5"></div>
    <button type="button" class="btn login-btn w-100" id="resendBtn">Запросить новый код</button>
  </div>
</div>
<?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
