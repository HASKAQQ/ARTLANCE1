<?php
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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

function getUserProfileCategories(int $userId): array
{
    $conn = getCategoryDbConnection();
    $stmt = $conn->prepare('SELECT c.id, c.name
        FROM profile_categories pc
        INNER JOIN categories c ON c.id = pc.category_id
        WHERE pc.profile_user_id = ?
        ORDER BY c.name');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

function addCategoryToProfile(int $userId, int $categoryId): void
{
    $conn = getCategoryDbConnection();
    $stmt = $conn->prepare('INSERT IGNORE INTO profile_categories (profile_user_id, category_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $categoryId);
    $stmt->execute();
}

function removeCategoryFromProfile(int $userId, int $categoryId): void
{
    $conn = getCategoryDbConnection();
    $stmt = $conn->prepare('DELETE FROM profile_categories WHERE profile_user_id = ? AND category_id = ?');
    $stmt->bind_param('ii', $userId, $categoryId);
    $stmt->execute();
}

$currentUserId = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if ($_POST['action'] === 'list_categories') {
            echo json_encode([
                'success' => true,
                'categories' => getAllCategories(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_POST['action'] === 'add_category') {
            $categoryName = $_POST['category_name'] ?? '';
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

            if ($categoryId > 0) {
                addCategoryToProfile($currentUserId, $categoryId);
            } else {
                $newCategoryId = createCategory($categoryName, $currentUserId);
                addCategoryToProfile($currentUserId, $newCategoryId);
            }

            echo json_encode([
                'success' => true,
                'profile_categories' => getUserProfileCategories($currentUserId),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_POST['action'] === 'remove_category') {
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            if ($categoryId > 0) {
                removeCategoryFromProfile($currentUserId, $categoryId);
            }

            echo json_encode([
                'success' => true,
                'profile_categories' => getUserProfileCategories($currentUserId),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new RuntimeException('Неизвестное действие.');
    } catch (Throwable $exception) {
        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$profileCategories = getUserProfileCategories($currentUserId);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>

<body>
  <?php include 'header.php'; ?>

  <!-- Профиль -->
  <section class="profile-section">
    <div class="container py-5">
      <!-- Профильная карточка -->
      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="src/image/Ellipse 2.png" alt="Avatar" class="profile-avatar" id="avatarImage">
            <div class="avatar-overlay position-absolute">
              <span class="avatar-overlay-text">Сменить<br>аватар</span>
            </div>
          </div>
          <div class="profile-contacts">
            <a href=""><img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram"></a>
            <a href=""><img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp"></a>
            <a href=""><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email"></a>
          </div>
          <div class="profile-balance">
            <span class="balance-label">Баланс, руб</span>
            <div class="balance-amount">
              <img src="src/image/icons/icons8-карточка-в-использовании-100 (1) 1.svg" alt="Wallet">
              <span>0</span>
            </div>
            <div class="balance-buttons d-flex justify-content-between flex-wrap">
              <button class="btn-balance">Вывести</button>
              <button class="btn-balance">Пополнить</button>
            </div>
          </div>
        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="d-flex align-items-center gap-3 mb-1">
            <input type="text" class="profile-name-input" value="Екатерина Кравчюк" id="profileName">
            <div class="profile-role-toggle">
              <button class="role-btn active" data-role="artist">Художник <img
                  src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn" data-role="client">Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg"
                  alt=""></button>
            </div>
          </div>

          <p class="profile-registration">Дата регистрации</p>

          <div class="profile-tags" id="profileTagsContainer">
            <?php foreach ($profileCategories as $category): ?>
              <div class="profile-tag-item" data-category-id="<?= (int) $category['id']; ?>">
                <p class="profile-tag"><?= htmlspecialchars($category['name']); ?></p>
                <button type="button" class="profile-tag-remove" onclick="removeProfileCategory(<?= (int) $category['id']; ?>)">×</button>
              </div>
            <?php endforeach; ?>
            <button type="button" class="profile-tag-add" onclick="openCategoryModal()">+</button>
          </div>

          <textarea class="profile-description" placeholder="О себе..."></textarea>
          <button class="btn-save-profile">Сохранить</button>
        </div>

      </div>

      <!-- Заказы -->
      <div class="section-collapsible" id="ordersSection">
        <div class="section-header" onclick="toggleSection('orders')">
          <h2>Заказы</h2>
          <span class="toggle-arrow" id="ordersArrow">▼</span>
        </div>
        <div class="section-content" id="ordersContent">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
          </div>

          <div class="text-center mt-4">
            <button class="btn-view-all">Смотреть всё</button>
          </div>
        </div>
      </div>

      <!-- Портфолио -->
      <div class="section-collapsible" id="portfolioSection">
        <div class="section-header" onclick="toggleSection('portfolio')">
          <div class="section-title">
            <h2>Портфолио</h2>
            <button class="btn-add-card" onclick="openPortfolioModal()">+</button>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="portfolioArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="portfolioContent">
          <div class="gallary-wrapper row g-3">
            <div class="col-4 col-lg-3">
              <div class="portfolio-card editable" onclick="openPortfolioModal(this)">
                <img src="src/image/Rectangle 55.png" alt="Portfolio" class="portfolio-image">
                <div class="portfolio-edit-overlay">
                  <p>Редактировать</p>
                </div>
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card" onclick="openPortfolioModal(this)">
                <img src="src/image/Rectangle 76.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card" onclick="openPortfolioModal(this)">
                <img src="src/image/Rectangle 78.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card" onclick="openPortfolioModal(this)">
                <img src="src/image/Rectangle 76.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card" onclick="openPortfolioModal(this)">
                <img src="src/image/Rectangle 55.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card add-card" onclick="openPortfolioModal()">
                <div class="portfolio-add-overlay">
                  <p class="add-icon">Добавить</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Услуги -->
      <div class="section-collapsible" id="servicesSection">
        <div class="section-header" onclick="toggleSection('services')">
          <div class="section-title">
            <h2>Услуги</h2>
            <button class="btn-add-card" onclick="openServiceModal()">+</button>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="servicesArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="servicesContent">
          <div class="services-grid row">
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100 editable" onclick="openServiceModal(this)">
                <img src="src/image/Rectangle 55.png" alt="Service" class="service-image">
                <div class="service-edit-overlay">
                  <p>Редактировать</p>
                </div>
                <div class="service-info">
                  <h3 class="service-title">Название услуги</h3>
                  <p class="service-category">3D-моделирование</p>
                  <div class="service-bottom">
                    <p class="service-price">от 30 000р</p>
                    <p class="service-time">3 часа назад</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100" onclick="openServiceModal(this)">
                <img src="src/image/Rectangle 76.png" alt="Service" class="service-image">
                <div class="service-info">
                  <h3 class="service-title">Название услуги</h3>
                  <p class="service-category">3D-моделирование</p>
                  <div class="service-bottom">
                    <p class="service-price">от 30 000р</p>
                    <p class="service-time">3 часа назад</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100 add-card" onclick="openServiceModal()">
                <p class="add-icon">Добавить</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Отзывы -->
      <div class="section-collapsible" id="reviewsSection">
        <div class="section-header" onclick="toggleSection('reviews')">
          <h2>Отзывы</h2>
          <span class="toggle-arrow" id="reviewsArrow">▼</span>
        </div>
        <div class="section-content" id="reviewsContent">
          <div class="reviews-list">
            <div class="review-card">
              <img src="src/image/Ellipse 2.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Ермакова Мария</h4>
                <p class="review-text">Большое спасибо! Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Елько Александр</h4>
                <p class="review-text">Большое спасибо!</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 3.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Строгая Наталья</h4>
                <p class="review-text">Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 4.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Лисицин Ванечка</h4>
                <p class="review-text">СУПЕР КЛАСС ЛАЙК РЕСПЕКТ. ОЧЕНЬ КРУТО СДЕЛАЛА И НЕ ДОРОГО. БЕРИТЕ НЕ
                  ПОЖАЛЕЕТЕ!!!!!!!!!!!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Модальное окно для портфолио -->
  <div class="modal-overlay" id="portfolioModal" onclick="closeModalOnOverlay(event, 'portfolioModal')">
    <div class="modal-content">
      <h3 class="modal-title">Портфолио</h3>
      <input type="text" class="modal-input" placeholder="Название работы">
      <div class="modal-image-upload large">
        <span>Добавить изображение</span>
      </div>
      <div class="modal-buttons">
        <button class="btn-modal-save" onclick="savePortfolio()">Сохранить</button>
        <button class="btn-modal-delete" onclick="deletePortfolio()">Удалить</button>
      </div>
    </div>
  </div>

  <!-- Модальное окно для услуг -->
  <div class="modal-overlay" id="serviceModal" onclick="closeModalOnOverlay(event, 'serviceModal')">
    <div class="modal-content modal-content-large">
      <h3 class="modal-title">Создание услуги</h3>
      <div class="modal-image-upload">
        <span>Добавить изображение</span>
      </div>
      <input type="text" class="modal-input" placeholder="Название услуги">
      <input type="text" class="modal-input" placeholder="Категория">
      <div class="input-group mb-3">
        <span class="input-group-text" id="basic-addon1">Цена</span>
        <input type="text" class="form-control" aria-label="Имя пользователя"
          aria-describedby="basic-addon1">
      </div>
      <textarea class="modal-textarea" placeholder="Подробное описание..."></textarea>
      <div class="modal-buttons">
        <button class="btn-modal-save" onclick="saveService()">Сохранить</button>
        <button class="btn-modal-delete" onclick="deleteService()">Удалить</button>
      </div>
    </div>
  </div>

  <div class="dropdown-edit" id="portfolioModalDropdown"></div>
  <div class="dropdown-edit" id="serviceModalDropdown"></div>

  <!-- Футер -->
    <div id="footer-placeholder"></div>


  <div class="modal-overlay" id="categoryModal" onclick="closeModalOnOverlay(event, 'categoryModal')">
    <div class="modal-content category-modal-content">
      <h3>Категории профиля</h3>
      <select id="existingCategorySelect" class="form-control mb-3">
        <option value="">Выберите существующую категорию</option>
      </select>
      <input type="text" id="customCategoryInput" class="form-control mb-3" placeholder="Или создайте свою категорию">
      <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Отмена</button>
        <button type="button" class="btn btn-primary" onclick="saveProfileCategory()">Сохранить</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
