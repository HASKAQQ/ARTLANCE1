<?php
session_start();

// Обработка отправки номера телефона
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'send_code') {
        $phone = $_POST['phone'] ?? '';

        // Генерируем случайный 5-значный код
        $code = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);

        // Сохраняем в сессии
        $_SESSION['verification_code'] = $code;
        $_SESSION['phone'] = $phone;
        $_SESSION['code_time'] = time();

        echo json_encode([
            'success' => true,
            'code' => $code // В реальном приложении этого не должно быть!
        ]);
        exit;
    }

    if ($_POST['action'] === 'verify_code') {
        $enteredCode = $_POST['code'] ?? '';
        $savedCode = $_SESSION['verification_code'] ?? '';

        if ($enteredCode === $savedCode) {
            // Код правильный - создаем пользователя/сессию
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_phone'] = $_SESSION['phone'];

            echo json_encode([
                'success' => true,
                'redirect' => 'profile-artist-edit.php'
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
