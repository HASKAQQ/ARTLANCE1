<?php
function getCategoryDbConnection(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $hosts = ['localhost', '127.0.0.1', 'MySQL-8.0'];
    $user = 'root';
    $password = '';

    foreach ($hosts as $host) {
        $conn = @new mysqli($host, $user, $password);
        if (!$conn->connect_error) {
            break;
        }
    }

    if (!$conn || $conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL.');
    }

    $conn->query('CREATE DATABASE IF NOT EXISTS artlance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $conn->select_db('artlance');
    $conn->set_charset('utf8mb4');

    initializeCategorySchema($conn);

    return $conn;
}

function initializeCategorySchema(mysqli $conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        display_name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_by (created_by_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS profile_categories (
        profile_user_id INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (profile_user_id, category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $usersCount = (int) $conn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'];
    if ($usersCount === 0) {
        $conn->query("INSERT INTO users (display_name) VALUES ('Екатерина Кравчюк')");
    }
}

function getAllCategories(): array
{
    $conn = getCategoryDbConnection();
    $result = $conn->query('SELECT c.id, c.name, c.created_by_user_id, u.display_name AS created_by_name
        FROM categories c
        LEFT JOIN users u ON u.id = c.created_by_user_id
        ORDER BY c.name');

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function createCategory(string $name, ?int $createdByUserId): int
{
    $conn = getCategoryDbConnection();
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new InvalidArgumentException('Название категории не может быть пустым.');
    }

    $stmtCheck = $conn->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmtCheck->bind_param('s', $normalizedName);
    $stmtCheck->execute();
    $existing = $stmtCheck->get_result()->fetch_assoc();

    if ($existing) {
        return (int) $existing['id'];
    }

    $stmtInsert = $conn->prepare('INSERT INTO categories (name, created_by_user_id) VALUES (?, ?)');
    $stmtInsert->bind_param('si', $normalizedName, $createdByUserId);
    $stmtInsert->execute();

    return (int) $conn->insert_id;
}

function updateCategoryName(int $categoryId, string $name): void
{
    $conn = getCategoryDbConnection();
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new InvalidArgumentException('Название категории не может быть пустым.');
    }

    $stmt = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
    $stmt->bind_param('si', $normalizedName, $categoryId);
    $stmt->execute();
}

function deleteCategory(int $categoryId): void
{
    $conn = getCategoryDbConnection();

    $stmtProfile = $conn->prepare('DELETE FROM profile_categories WHERE category_id = ?');
    $stmtProfile->bind_param('i', $categoryId);
    $stmtProfile->execute();

    $stmtCategory = $conn->prepare('DELETE FROM categories WHERE id = ?');
    $stmtCategory->bind_param('i', $categoryId);
    $stmtCategory->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $action = $_POST['admin_action'];

    try {
        if ($action === 'add') {
            $name = $_POST['category_name'] ?? '';
            createCategory($name, null);
        }

        if ($action === 'edit') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = $_POST['category_name'] ?? '';
            if ($categoryId > 0) {
                updateCategoryName($categoryId, $name);
            }
        }

        if ($action === 'delete') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            if ($categoryId > 0) {
                deleteCategory($categoryId);
            }
        }
    } catch (Throwable $exception) {
        $adminError = $exception->getMessage();
    }
}

$categories = getAllCategories();
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
            <img src="src/image/Ellipse 4.png" alt="" class="logo">
        </div>
    </div>

    <div class="admin">
        <div class="container adm-cont-table">
            <?php if (!empty($adminError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($adminError); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <table class="table align-middle">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">Категория</th>
                                <th scope="col">Кто добавил</th>
                                <th scope="col">Редактировать</th>
                                <th scope="col">Профиль автора</th>
                                <th scope="col">Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr class="align-middle">
                                    <td>
                                        <form method="post" class="d-flex gap-2">
                                            <input type="hidden" name="admin_action" value="edit">
                                            <input type="hidden" name="category_id" value="<?= (int) $category['id']; ?>">
                                            <input type="text" name="category_name" value="<?= htmlspecialchars($category['name']); ?>" class="form-control">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Изменить</button>
                                        </form>
                                    </td>
                                    <td><?= $category['created_by_user_id'] ? htmlspecialchars($category['created_by_name'] ?? 'Пользователь') : 'Админ'; ?></td>
                                    <td>—</td>
                                    <td>
                                        <?php if ($category['created_by_user_id']): ?>
                                            <a href="profile-artist.php?user_id=<?= (int) $category['created_by_user_id']; ?>" class="btn btn-sm btn-outline-secondary">Профиль</a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Профиль</button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Удалить категорию?');">
                                            <input type="hidden" name="admin_action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int) $category['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr>
                                <td colspan="5">
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="admin_action" value="add">
                                        <input type="text" name="category_name" class="form-control" placeholder="Новая категория" required>
                                        <button type="submit" class="btn admin-btn">Добавить категорию</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
