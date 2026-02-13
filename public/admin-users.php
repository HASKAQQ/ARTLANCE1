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
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

$errorMessage = '';
$successMessage = '';
$users = [];
$viewUser = null;

try {
    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($action === 'edit_phone' && $userId > 0) {
            $newPhone = normalizePhone((string) ($_POST['new_phone'] ?? ''));

            if (strlen($newPhone) !== 11) {
                $errorMessage = 'Номер должен содержать ровно 11 цифр.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET phone = ? WHERE id = ?');
                $stmt->bind_param('si', $newPhone, $userId);
                if ($stmt->execute()) {
                    $successMessage = 'Номер пользователя обновлён.';
                } else {
                    $errorMessage = 'Не удалось обновить номер: ' . $stmt->error;
                }
            }
        }

        if ($action === 'toggle_block' && $userId > 0) {
            $stmt = $conn->prepare('UPDATE users SET is_blocked = IF(is_blocked = 1, 0, 1) WHERE id = ?');
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $successMessage = 'Статус блокировки пользователя обновлён.';
            } else {
                $errorMessage = 'Не удалось обновить блокировку: ' . $stmt->error;
            }
        }
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    if ($query !== '') {
        $search = '%' . $query . '%';
        $stmt = $conn->prepare('SELECT id, name, phone, role, is_blocked, registered_at FROM users WHERE name LIKE ? OR phone LIKE ? ORDER BY id DESC');
        $stmt->bind_param('ss', $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query('SELECT id, name, phone, role, is_blocked, registered_at FROM users ORDER BY id DESC');
    }

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    $viewId = (int) ($_GET['view_id'] ?? 0);
    if ($viewId > 0) {
        $stmt = $conn->prepare('SELECT id, name, phone, role, is_blocked, registered_at FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $viewUser = $stmt->get_result()->fetch_assoc() ?: null;
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
    <title>ARTlance — фриланс-биржа для художников</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/main.js" defer></script>
</head>

<body>
    <div class="admin-nav">
        <div class="nav-menu">
            <h1 class="admin-menu-title">Панель администратора</h1>
            <div class="admin-menu-links">
                <a href="admin-main.php" class="admin-menu-link">Главная</a>
                <a href="admin-users.php" class="admin-menu-link">Пользователи</a>
                <a href="admin-services.php" class="admin-menu-link">Услуги</a>
                <a href="admin-orders.php" class="admin-menu-link">Заказы</a>
                <a href="admin-transactions.php" class="admin-menu-link">Транзакции</a>
            </div>
        </div>
        <div class="container nav-container">
            <img src="src/image/icons/Group 27.svg" alt="" class="menu-btn">
            <div class="admin-user-menu" id="adminUserMenu">
                <button class="admin-avatar-btn" id="adminAvatarBtn" type="button" aria-label="Меню администратора"></button>
                <div class="admin-user-dropdown" id="adminUserDropdown">
                    <a href="logout.php" class="admin-user-dropdown-item">Выйти</a>
                </div>
            </div>
        </div>
    </div>
    <div class="admin adm-t">
        <div class="container adm-cont-table">
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success mt-3"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="row">
                <div class="col-12 col-lg-6">
                    <form class="admin-search-wrapper" method="get">
                        <input type="text" name="q" class="form-control admin-search-input" placeholder="Поиск художников" value="<?php echo htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="admin-search-btn" type="submit">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                    stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            <div class="row desktop">
                <div class="col-12">
                    <table class="table align-middle big-table">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">ID</th>
                                <th scope="col">Пользователь</th>
                                <th scope="col">Телефон</th>
                                <th scope="col">Роль</th>
                                <th scope="col">Дата регистрации</th>
                                <th scope="col">Редактировать</th>
                                <th scope="col">Смотреть <br> профиль</th>
                                <th scope="col">Заблокировать</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="align-middle">
                                <td><?php echo (int) $user['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) ($user['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <button type="button" class="btn p-0 border-0 bg-transparent" onclick="editUserPhone(<?php echo (int) $user['id']; ?>, '<?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <img src="src/image/icons/icons8-редактировать-100 1.svg" alt="Редактировать">
                                    </button>
                                </td>
                                <td>
                                    <a href="admin-users.php?view_id=<?php echo (int) $user['id']; ?>" class="d-inline-block">
                                        <img src="src/image/icons/icons8-показать-100 1.svg" alt="Смотреть профиль">
                                    </a>
                                </td>
                                <td>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <button type="submit" class="btn p-0 border-0 bg-transparent" title="<?php echo ((int) $user['is_blocked'] === 1) ? 'Разблокировать' : 'Заблокировать'; ?>">
                                            <img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="Заблокировать" style="opacity: <?php echo ((int) $user['is_blocked'] === 1) ? '0.35' : '1'; ?>;">
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="container mob">
            <?php foreach ($users as $user): ?>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info"><?php echo (int) $user['id']; ?></div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Пользователь</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) ($user['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Телефон</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Роль</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions d-flex gap-2">
                            <button type="button" class="btn p-0 border-0 bg-transparent" onclick="editUserPhone(<?php echo (int) $user['id']; ?>, '<?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?>')"><img src="src/image/icons/icons8-редактировать-100 1.svg" alt=""></button>
                            <a href="admin-users.php?view_id=<?php echo (int) $user['id']; ?>"><img src="src/image/icons/icons8-показать-100 1.svg" alt=""></a>
                            <form method="post" class="m-0 d-inline">
                                <input type="hidden" name="action" value="toggle_block">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <button type="submit" class="btn p-0 border-0 bg-transparent"><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="" style="opacity: <?php echo ((int) $user['is_blocked'] === 1) ? '0.35' : '1'; ?>;"></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form id="editPhoneForm" method="post" class="d-none">
        <input type="hidden" name="action" value="edit_phone">
        <input type="hidden" name="user_id" id="editPhoneUserId">
        <input type="hidden" name="new_phone" id="editPhoneValue">
    </form>

    <?php if ($viewUser): ?>
      <div class="modal fade show" style="display:block; background: rgba(0,0,0,0.4);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Профиль пользователя</h5>
              <a href="admin-users.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
              <p><b>ID:</b> <?php echo (int) $viewUser['id']; ?></p>
              <p><b>Имя:</b> <?php echo htmlspecialchars((string) ($viewUser['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Телефон:</b> <?php echo htmlspecialchars((string) $viewUser['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Роль:</b> <?php echo htmlspecialchars((string) $viewUser['role'], ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Дата регистрации:</b> <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $viewUser['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Статус:</b> <?php echo ((int) $viewUser['is_blocked'] === 1) ? 'Заблокирован' : 'Активен'; ?></p>
            </div>
            <div class="modal-footer">
              <a href="admin-users.php" class="btn btn-secondary">Закрыть</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <script>
      function editUserPhone(userId, currentPhone) {
        const newPhone = prompt('Введите новый номер (11 цифр):', currentPhone || '');
        if (newPhone === null) return;

        const digits = String(newPhone).replace(/\D/g, '');
        if (digits.length !== 11) {
          alert('Номер должен содержать ровно 11 цифр.');
          return;
        }

        document.getElementById('editPhoneUserId').value = userId;
        document.getElementById('editPhoneValue').value = digits;
        document.getElementById('editPhoneForm').submit();
      }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
