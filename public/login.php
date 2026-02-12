<?php
session_start();

function normalizePhone(string $phone): string
{
    $digitsOnly = preg_replace('/\D+/', '', $phone);

    if ($digitsOnly === null) {
        return '';
    }

    if (strlen($digitsOnly) === 11 && $digitsOnly[0] === '8') {
        $digitsOnly = '7' . substr($digitsOnly, 1);
    }

    if (strlen($digitsOnly) !== 11 || $digitsOnly[0] !== '7') {
        return '';
    }

    return '+' . $digitsOnly;
}

function getDbConnection(): ?mysqli
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $name = getenv('DB_NAME') ?: 'artlance';

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($host, $user, $pass, $name);

    if ($conn->connect_error) {
        error_log('DB connection failed: ' . $conn->connect_error);
        return null;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function findOrCreateUserByPhone(string $phone): ?array
{
    $conn = getDbConnection();

    if (!$conn) {
        return null;
    }

    $select = $conn->prepare('SELECT id, user_name, user_role FROM users WHERE user_tel = ? LIMIT 1');
    if (!$select) {
        $conn->close();
        return null;
    }

    $select->bind_param('s', $phone);
    $select->execute();
    $result = $select->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $select->close();

    if ($user) {
        $conn->close();
        return $user;
    }

    $defaultName = 'Новый пользователь';
    $defaultRole = 'заказчик';
    $registeredAt = date('Y-m-d H:i:s');

    $insert = $conn->prepare('INSERT INTO users (user_name, user_tel, user_role, user_registration_date) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        $conn->close();
        return null;
    }

    $insert->bind_param('ssss', $defaultName, $phone, $defaultRole, $registeredAt);
    $success = $insert->execute();
    $newId = $conn->insert_id;
    $insert->close();
    $conn->close();

    if (!$success) {
        return null;
    }

    return [
        'id' => $newId,
        'user_name' => $defaultName,
        'user_role' => $defaultRole,
    ];
}

// Обработка отправки номера телефона
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'send_code') {
        $phone = normalizePhone($_POST['phone'] ?? '');

        if ($phone === '') {
            echo json_encode([
                'success' => false,
                'message' => 'Введите корректный номер в формате +7XXXXXXXXXX',
            ]);
            exit;
        }

        $lastSentAt = $_SESSION['code_time'] ?? 0;
        if ($lastSentAt && (time() - $lastSentAt) < 30) {
            echo json_encode([
                'success' => false,
                'message' => 'Повторно запросить код можно через 30 секунд',
            ]);
            exit;
        }

        // Генерируем случайный 5-значный код
        $code = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);

        // Сохраняем в сессии
        $_SESSION['verification_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['phone'] = $phone;
        $_SESSION['code_time'] = time();
        $_SESSION['verification_attempts'] = 0;

        // В демо-режиме код пишется в лог OpenServer/PHP.
        error_log('ARTlance SMS code for ' . $phone . ': ' . $code);

        $response = [
            'success' => true,
            'message' => 'Код отправлен. Проверьте SMS.',
        ];

        if (($_SERVER['SERVER_NAME'] ?? '') === 'localhost') {
            $response['debug_code'] = $code;
        }

        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'verify_code') {
        $enteredCode = trim($_POST['code'] ?? '');
        $savedCodeHash = $_SESSION['verification_code_hash'] ?? '';
        $codeTime = $_SESSION['code_time'] ?? 0;
        $attempts = $_SESSION['verification_attempts'] ?? 0;

        if (!preg_match('/^\d{5}$/', $enteredCode)) {
            echo json_encode([
                'success' => false,
                'message' => 'Код должен состоять из 5 цифр',
            ]);
            exit;
        }

        if (!$savedCodeHash || !$codeTime || (time() - $codeTime) > 300) {
            echo json_encode([
                'success' => false,
                'message' => 'Срок действия кода истёк. Запросите новый код.',
            ]);
            exit;
        }

        if ($attempts >= 5) {
            echo json_encode([
                'success' => false,
                'message' => 'Превышено число попыток. Запросите новый код.',
            ]);
            exit;
        }

        $_SESSION['verification_attempts'] = $attempts + 1;

        if (password_verify($enteredCode, $savedCodeHash)) {
            // Код правильный - создаем пользователя/сессию
            $phone = $_SESSION['phone'] ?? '';
            $user = $phone ? findOrCreateUserByPhone($phone) : null;

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_phone'] = $phone;
            $_SESSION['user_id'] = $user['id'] ?? null;
            $_SESSION['user_role'] = $user['user_role'] ?? 'заказчик';

            unset($_SESSION['verification_code_hash'], $_SESSION['verification_attempts'], $_SESSION['code_time']);

            echo json_encode([
                'success' => true,
                'redirect' => 'profile-client-edit.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Неверный код'
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - ARTlance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>

</head>
<body>
  <?php include 'header.php'; ?>

    <div class="login-page">
        <div class="container-fluid h-100">
            <div class="row justify-content-center align-items-center h-100">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="login-card" id="phoneStep">
                        <h1 class="login-title">Войти или создать профиль</h1>
                        <p id="loginMessage" class="login-message" aria-live="polite"></p>

                        <form id="phoneForm">
                            <div class="mb-4">
                                <input type="tel" class="form-control login-input" id="phoneInput" placeholder="Телефон" required>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                <label class="form-check-label terms-label" for="termsCheck">
                                    Я ознакомлен(а), понимаю и принимаю <a href="#" class="terms-link">правила для художников и заказчиков</a>
                                </label>
                            </div>

                            <button type="submit" class="btn login-btn w-100">Получить код</button>
                        </form>
                    </div>

                    <div class="login-card d-none" id="codeStep">
                        <h1 class="code-title">Откройте сообщения на телефоне<br>и введите код</h1>
                        <p id="codeMessage" class="login-message" aria-live="polite"></p>

                        <form id="codeForm">
                            <div class="code-inputs mb-4">
                                <input type="text" class="code-input" maxlength="1" id="code1" required>
                                <input type="text" class="code-input" maxlength="1" id="code2" required>
                                <input type="text" class="code-input" maxlength="1" id="code3" required>
                                <input type="text" class="code-input" maxlength="1" id="code4" required>
                                <input type="text" class="code-input" maxlength="1" id="code5" required>
                            </div>

                            <button type="button" class="btn login-btn w-100" id="resendBtn">Запросить новый код</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
