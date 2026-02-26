<?php
require_once __DIR__ . '/includes/category_repository.php';

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
                                            <a href="profile-artist-edit.php?user_id=<?= (int) $category['created_by_user_id']; ?>" class="btn btn-sm btn-outline-secondary">Профиль</a>
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
