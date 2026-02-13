<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

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
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

$users = [];
$errorMessage = '';

try {
    $conn = getDbConnection();
    $result = $conn->query('SELECT id, name, phone, role, registered_at FROM users ORDER BY id DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи — ARTlance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="admin-nav">
    <header class="header admin-header-no-border" id="header">
        <nav class="navbar navbar-expand-lg navbar-light bg-white">
            <div class="container nav-container">
                <button type="button" class="menu-btn" aria-label="Открыть навигацию">
                    <img src="src/image/icons/Group 27.svg" alt="menu">
                </button>
                <a href="index.php" class="logo navbar-brand text-decoration-none admin-header-logo">ARTlance</a>
                <div class="admin-user-menu" id="adminUserMenu">
                    <button class="admin-avatar-btn" id="adminAvatarBtn" type="button" aria-label="Меню администратора"></button>
                    <div class="admin-user-dropdown" id="adminUserDropdown">
                        <a href="logout.php" class="admin-user-dropdown-item">Выйти</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="nav-menu">
        <div class="admin-side-header">
            <button type="button" class="admin-menu-close" aria-label="Закрыть навигацию">✕</button>
        </div>
        <h1 class="admin-menu-title">Панель администратора</h1>
        <div class="admin-menu-links">
            <a href="admin-main.php" class="admin-menu-link">Главная</a>
            <a href="admin-users.php" class="admin-menu-link">Пользователи</a>
            <a href="admin-services.php" class="admin-menu-link">Услуги</a>
            <a href="admin-orders.php" class="admin-menu-link">Заказы</a>
            <a href="admin-transactions.php" class="admin-menu-link">Транзакции</a>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 admin-users-topbar">
        <h1 class="mb-0">Панель администратора — Пользователи</h1>
    </div>

    <div class="mb-3 d-flex gap-2">
        <a href="admin-main.php" class="btn btn-outline-dark">Главная</a>
        <a href="admin-users.php" class="btn btn-dark">Пользователи</a>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
            <tr>
                <th>ID</th>
                <th>Пользователь</th>
                <th>Телефон</th>
                <th>Роль</th>
                <th>Дата регистрации</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($users) === 0): ?>
                <tr>
                    <td colspan="5" class="text-center">Пока нет пользователей</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo (int) $user['id']; ?></td>
                        <td><?php echo htmlspecialchars((string) ($user['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/main.js"></script>
</body>
</html>
