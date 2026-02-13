<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

const ADMIN_PHONE = '79930170672';

function getDbConnection(): mysqli
{
    $conn = new mysqli('MySQL-8.0', 'root', '');

    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query('CREATE DATABASE IF NOT EXISTS artlance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) DEFAULT NULL,
            role VARCHAR(30) NOT NULL DEFAULT "Художник",
            avatar_path VARCHAR(255) DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

function maskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) !== 11) {
        return 'Скрыт';
    }

    return substr($digits, 0, 1) . '********' . substr($digits, -2);
}

$errorMessage = '';
$user = null;

try {
    $conn = getDbConnection();

    $userId = (int) ($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Некорректный идентификатор пользователя.');
    }

    $stmt = $conn->prepare('SELECT id, name, phone, role, avatar_path, is_blocked, registered_at FROM users WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new RuntimeException('Пользователь не найден.');
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

$isAdminViewer = (bool) ($_SESSION['is_admin'] ?? false);
$viewerPhone = (string) ($_SESSION['user_phone'] ?? '');
$isOwner = $user && $viewerPhone !== '' && $viewerPhone === (string) $user['phone'];

$displayName = $user ? (string) ($user['name'] ?: 'Пользователь') : 'Профиль';
$displayRole = $user ? (string) ($user['role'] ?: 'Художник') : '';
$displayDate = $user ? date('d.m.Y', strtotime((string) $user['registered_at'])) : '';
$displayPhone = $user ? (($isAdminViewer || $isOwner) ? (string) $user['phone'] : maskPhone((string) $user['phone'])) : '';
$avatarPath = $user && !empty($user['avatar_path']) ? (string) $user['avatar_path'] : 'src/image/Ellipse 2.png';
$statusLabel = $user && (int) $user['is_blocked'] === 1 ? 'Заблокирован' : 'Активен';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль пользователя — ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>
<body>
  <?php include 'header.php'; ?>

  <section class="profile-section">
    <div class="container">
      <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <a href="admin-users.php" class="btn btn-secondary">Назад к пользователям</a>
      <?php else: ?>
        <div class="profile-card bg-white row">
          <div class="col-4 col-lg-3 profile-col-wrapper">
            <div class="profile-avatar-wrapper position-relative">
              <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="profile-avatar">
            </div>
          </div>

          <div class="profile-info col-8 col-lg-9">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <h3 class="profile-name mb-2"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h3>
                <span class="badge text-bg-light border"><?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="text-end">
                <a href="admin-users.php" class="btn btn-outline-secondary btn-sm">Назад</a>
              </div>
            </div>

            <hr>

            <p class="mb-2"><strong>Дата регистрации:</strong> <?php echo htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-2"><strong>Телефон:</strong> <?php echo htmlspecialchars($displayPhone, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mb-3"><strong>Статус:</strong> <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></p>

            <div class="alert alert-info py-2 mb-0" role="alert">
              Режим просмотра: профиль открыт только для чтения. Редактирование и чувствительные данные ограничены.
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
