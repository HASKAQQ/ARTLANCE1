<?php
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
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
        'CREATE TABLE IF NOT EXISTS phone_auth (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            verification_code CHAR(5) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

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

    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_services (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_phone (user_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

$userPhone = $_SESSION['user_phone'] ?? '';
$userName = '';
$avatarPath = '';
$registeredAt = '';
$saveMessage = '';
$errorMessage = '';
$services = [];

try {
    $conn = getDbConnection();

    if ($userPhone !== '' && isset($_GET['set_role'])) {
        $setRole = (string) $_GET['set_role'];
        if ($setRole === 'client') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Заказчик" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-client-edit.php');
            exit;
        }
        if ($setRole === 'artist') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Художник" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-artist-edit.php');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_required_name') {
        header('Content-Type: application/json');

        $name = trim((string) ($_POST['name'] ?? ''));

        if ($userPhone === '') {
            echo json_encode(['success' => false, 'message' => 'Сессия истекла. Войдите снова.']);
            exit;
        }

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Имя обязательно для заполнения.']);
            exit;
        }

        $registeredAtForInsert = date('Y-m-d H:i:s');
        $existingStmt = prepareOrFail($conn, 'SELECT registered_at FROM users WHERE phone = ? LIMIT 1');
        $existingStmt->bind_param('s', $userPhone);
        $existingStmt->execute();
        $existingRow = $existingStmt->get_result()->fetch_assoc();

        if ($existingRow && !empty($existingRow['registered_at'])) {
            $registeredAtForInsert = (string) $existingRow['registered_at'];
        } else {
            $authStmt = prepareOrFail($conn, 'SELECT created_at FROM phone_auth WHERE phone = ? ORDER BY id DESC LIMIT 1');
            $authStmt->bind_param('s', $userPhone);
            $authStmt->execute();
            $authRow = $authStmt->get_result()->fetch_assoc();
            if ($authRow && !empty($authRow['created_at'])) {
                $registeredAtForInsert = (string) $authRow['created_at'];
            }
        }

        $saveStmt = prepareOrFail(
            $conn,
            'INSERT INTO users (phone, name, role, registered_at) VALUES (?, ?, "Художник", ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role)'
        );
        $saveStmt->bind_param('sss', $userPhone, $name, $registeredAtForInsert);
        $saveStmt->execute();

        echo json_encode(['success' => true, 'name' => $name]);
        exit;
    }

    if ($userPhone !== '') {
        $stmt = prepareOrFail($conn, 'SELECT name, avatar_path, registered_at FROM users WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $userPhone);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $userName = (string) ($existing['name'] ?? '');
            $avatarPath = (string) ($existing['avatar_path'] ?? '');
            $registeredAt = (string) ($existing['registered_at'] ?? '');
        } else {
            $authStmt = prepareOrFail($conn, 'SELECT created_at FROM phone_auth WHERE phone = ? ORDER BY id DESC LIMIT 1');
            $authStmt->bind_param('s', $userPhone);
            $authStmt->execute();
            $authRow = $authStmt->get_result()->fetch_assoc();
            $registeredAt = (string) ($authRow['created_at'] ?? '');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_action'])) {
        $serviceAction = (string) $_POST['service_action'];

        if ($serviceAction === 'save_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $serviceTitle = trim((string) ($_POST['service_title'] ?? ''));
            $serviceCategory = trim((string) ($_POST['service_category'] ?? ''));
            $servicePrice = (float) str_replace(',', '.', (string) ($_POST['service_price'] ?? '0'));
            $serviceDescription = trim((string) ($_POST['service_description'] ?? ''));

            if ($serviceTitle === '' || $serviceCategory === '') {
                $errorMessage = 'Для услуги нужно заполнить название и категорию.';
            } else {
                $serviceImagePath = '';
                if ($serviceId > 0) {
                    $serviceImageStmt = prepareOrFail($conn, 'SELECT image_path FROM artist_services WHERE id = ? AND user_phone = ? LIMIT 1');
                    $serviceImageStmt->bind_param('is', $serviceId, $userPhone);
                    $serviceImageStmt->execute();
                    $serviceImageRow = $serviceImageStmt->get_result()->fetch_assoc();
                    $serviceImagePath = (string) ($serviceImageRow['image_path'] ?? '');
                }

                if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['service_image']['tmp_name'];
                    $mime = mime_content_type($tmpName);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (isset($allowed[$mime])) {
                        $uploadDir = __DIR__ . '/uploads/services';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileName = 'service_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '.' . $allowed[$mime];
                        $targetPath = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $serviceImagePath = 'uploads/services/' . $fileName;
                        }
                    }
                }

                if ($serviceId > 0) {
                    $upd = prepareOrFail($conn, 'UPDATE artist_services SET title = ?, category = ?, price = ?, description = ?, image_path = ? WHERE id = ? AND user_phone = ?');
                    $upd->bind_param('ssdssis', $serviceTitle, $serviceCategory, $servicePrice, $serviceDescription, $serviceImagePath, $serviceId, $userPhone);
                    $upd->execute();
                } else {
                    $ins = prepareOrFail($conn, 'INSERT INTO artist_services (user_phone, title, category, price, description, image_path) VALUES (?, ?, ?, ?, ?, ?)');
                    $ins->bind_param('sssdss', $userPhone, $serviceTitle, $serviceCategory, $servicePrice, $serviceDescription, $serviceImagePath);
                    $ins->execute();
                }

                $saveMessage = 'Услуга сохранена.';
            }
        }

        if ($serviceAction === 'delete_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            if ($serviceId > 0) {
                $del = prepareOrFail($conn, 'DELETE FROM artist_services WHERE id = ? AND user_phone = ?');
                $del->bind_param('is', $serviceId, $userPhone);
                $del->execute();
                $saveMessage = 'Услуга удалена.';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
        $name = trim((string) ($_POST['profile_name'] ?? ''));

        if ($name === '') {
            $errorMessage = 'Вы обязаны ввести имя перед сохранением профиля.';
        } else {
            $avatarForDb = $avatarPath;

            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['avatar_file']['tmp_name'];
                $mime = mime_content_type($tmpName);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (isset($allowed[$mime])) {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = 'avatar_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '.' . $allowed[$mime];
                    $targetPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $avatarForDb = 'uploads/avatars/' . $fileName;
                    }
                } else {
                    $errorMessage = 'Можно загрузить только JPG, PNG или WEBP.';
                }
            }

            if ($errorMessage === '') {
                $upsert = prepareOrFail(
                    $conn,
                    'INSERT INTO users (phone, name, role, avatar_path) VALUES (?, ?, "Художник", ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), avatar_path = VALUES(avatar_path), role = VALUES(role)'
                );
                $upsert->bind_param('sss', $userPhone, $name, $avatarForDb);
                $upsert->execute();

                $userName = $name;
                $avatarPath = $avatarForDb;

                $stmt = prepareOrFail($conn, 'SELECT registered_at FROM users WHERE phone = ? LIMIT 1');
                $stmt->bind_param('s', $userPhone);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $registeredAt = (string) ($row['registered_at'] ?? '');

                $saveMessage = 'Профиль сохранён.';
            }
        }
    }
    $servicesStmt = prepareOrFail($conn, 'SELECT id, title, category, price, description, image_path, created_at FROM artist_services WHERE user_phone = ? ORDER BY id DESC');
    $servicesStmt->bind_param('s', $userPhone);
    $servicesStmt->execute();
    $servicesRes = $servicesStmt->get_result();
    while ($serviceRow = $servicesRes->fetch_assoc()) {
        $services[] = $serviceRow;
    }

} catch (Throwable $e) {
    $errorMessage = 'Ошибка при сохранении профиля: ' . $e->getMessage();
}

$registrationLabel = $registeredAt !== '' ? 'Дата регистрации: ' . date('d.m.Y H:i', strtotime($registeredAt)) : 'Дата регистрации: ещё не заполнена';
$avatarSrc = $avatarPath !== '' ? htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
$showNameModal = $userName === '';
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
      <?php if ($errorMessage !== ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></div>
      <?php endif; ?>
      <?php if ($saveMessage !== ""): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($saveMessage, ENT_QUOTES, "UTF-8"); ?></div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" id="profileForm">
      <?php if ($showNameModal): ?>
        <div id="requiredNameModal" style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;">
          <div style="max-width:420px;width:100%;background:#fff;border-radius:16px;padding:24px;">
            <h3 class="mb-3">Заполните профиль</h3>
            <p class="mb-3">Для продолжения обязательно введите имя.</p>
            <input type="text" class="form-control mb-3" id="requiredNameInput" placeholder="Введите имя" maxlength="255">
            <button type="button" class="btn btn-dark w-100" id="requiredNameSaveBtn">Сохранить</button>
          </div>
        </div>
      <?php endif; ?>

      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="profile-avatar" id="avatarImage">
            <input type="file" accept="image/png,image/jpeg,image/webp" id="avatarFile" name="avatar_file" class="d-none">
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
            <input type="text" class="profile-name-input" value="<?php echo htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?>" id="profileName" name="profile_name" placeholder="Введите имя" required>
            <div class="profile-role-toggle">
              <button class="role-btn active" type="button" data-switch-url="profile-artist-edit.php?set_role=artist">Художник <img
                  src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn" type="button" data-switch-url="profile-client-edit.php?set_role=client">Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg"
                  alt=""></button>
            </div>
          </div>

          <p class="profile-registration"><?php echo htmlspecialchars($registrationLabel, ENT_QUOTES, "UTF-8"); ?></p>

          <div class="profile-tags">
            <p class="profile-tag">3D-моделирование и визуализация</p>
            <p class="profile-tag">Графический дизайн</p>
            <p class="profile-tag">Цифровая живопись</p>
            <button class="profile-tag-add">+</button>
          </div>

          <textarea class="profile-description" placeholder="О себе..."></textarea>
          <button class="btn-save-profile" type="submit" name="save_profile">Сохранить</button>
        </div>

      </div>
      </form>

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
            <button class="btn-add-card" type="button" onclick="event.stopPropagation(); openServiceEditor()">+</button>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="servicesArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="servicesContent">
          <div class="services-grid row">
            <?php foreach ($services as $service): ?>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100" onclick='openServiceEditor(<?php echo json_encode($service, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                <img src="<?php echo htmlspecialchars((string) ($service['image_path'] ?: 'src/image/Rectangle 55.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Service" class="service-image">
                <div class="service-info">
                  <h3 class="service-title"><?php echo htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <p class="service-category"><?php echo htmlspecialchars((string) $service['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                  <div class="service-bottom">
                    <p class="service-price">от <?php echo (int) $service['price']; ?>р</p>
                    <p class="service-time"><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $service['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100 add-card" onclick="openServiceEditor()">
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
    <div class="modal-content modal-content-large" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeServiceEditor()"></button>
      <h3 class="modal-title">Создание услуги</h3>
      <form method="post" enctype="multipart/form-data" id="serviceForm">
        <input type="hidden" name="service_action" id="serviceAction" value="save_service">
        <input type="hidden" name="service_id" id="serviceId" value="0">
        <div class="modal-image-upload">
          <span id="serviceImageLabel">Добавить изображение</span>
          <input type="file" name="service_image" id="serviceImage" class="d-none" accept="image/png,image/jpeg,image/webp">
        </div>
        <input type="text" class="modal-input" name="service_title" id="serviceTitle" placeholder="Название услуги" required>
        <input type="text" class="modal-input" name="service_category" id="serviceCategory" placeholder="Категория" required>
        <div class="input-group mb-3">
          <span class="input-group-text">Цена</span>
          <input type="text" class="form-control" name="service_price" id="servicePrice" placeholder="0">
        </div>
        <textarea class="modal-textarea" name="service_description" id="serviceDescription" placeholder="Подробное описание..."></textarea>
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit">Сохранить</button>
          <button class="btn-modal-delete" type="button" onclick="deleteServiceItem()">Удалить</button>
        </div>
      </form>
    </div>
  </div>

  <div class="dropdown-edit" id="portfolioModalDropdown"></div>

  <div class="dropdown-edit" id="serviceModalDropdown"></div>

  <!-- Футер -->
    <div id="footer-placeholder"></div>

  <script>
    const avatarImageEl = document.getElementById('avatarImage');
    const avatarFileInput = document.getElementById('avatarFile');
    const avatarOverlay = document.querySelector('.avatar-overlay');

    if (avatarImageEl && avatarFileInput) {
      avatarImageEl.style.cursor = 'pointer';
      avatarImageEl.addEventListener('click', () => avatarFileInput.click());
    }

    if (avatarOverlay && avatarFileInput) {
      avatarOverlay.style.cursor = 'pointer';
      avatarOverlay.addEventListener('click', () => avatarFileInput.click());
    }

    if (avatarFileInput && avatarImageEl) {
      avatarFileInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
          const fileUrl = URL.createObjectURL(this.files[0]);
          avatarImageEl.src = fileUrl;
        }
      });
    }

    const requiredNameModal = document.getElementById('requiredNameModal');
    const requiredNameInput = document.getElementById('requiredNameInput');
    const requiredNameSaveBtn = document.getElementById('requiredNameSaveBtn');
    const profileNameInput = document.getElementById('profileName');
    const profileForm = document.getElementById('profileForm');

    if (requiredNameModal && requiredNameInput && requiredNameSaveBtn && profileNameInput && profileForm) {
      const submitNameFromModal = function () {
        const enteredName = requiredNameInput.value.trim();

        if (enteredName === '') {
          alert('Имя обязательно для заполнения.');
          requiredNameInput.focus();
          return;
        }

        const body = new URLSearchParams();
        body.set('action', 'save_required_name');
        body.set('name', enteredName);

        fetch('profile-artist-edit.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: body.toString()
        })
          .then(response => response.json())
          .then(data => {
            if (!data.success) {
              alert(data.message || 'Ошибка сохранения имени');
              return;
            }

            profileNameInput.value = data.name || enteredName;
            requiredNameModal.remove();
          })
          .catch(() => {
            alert('Ошибка сохранения имени. Попробуйте ещё раз.');
          });
      };

      requiredNameSaveBtn.addEventListener('click', submitNameFromModal);
      requiredNameInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          submitNameFromModal();
        }
      });
    }


    const serviceModalEl = document.getElementById('serviceModal');
    const serviceIdEl = document.getElementById('serviceId');
    const serviceTitleEl = document.getElementById('serviceTitle');
    const serviceCategoryEl = document.getElementById('serviceCategory');
    const servicePriceEl = document.getElementById('servicePrice');
    const serviceDescriptionEl = document.getElementById('serviceDescription');
    const serviceImageEl = document.getElementById('serviceImage');
    const serviceImageLabelEl = document.getElementById('serviceImageLabel');

    function openServiceEditor(serviceData = null) {
      if (!serviceModalEl) return;
      if (serviceIdEl) serviceIdEl.value = '0';
      if (serviceTitleEl) serviceTitleEl.value = '';
      if (serviceCategoryEl) serviceCategoryEl.value = '';
      if (servicePriceEl) servicePriceEl.value = '';
      if (serviceDescriptionEl) serviceDescriptionEl.value = '';
      if (serviceImageEl) serviceImageEl.value = '';
      if (serviceImageLabelEl) serviceImageLabelEl.textContent = 'Добавить изображение';

      if (serviceData && typeof serviceData === 'object') {
        if (serviceIdEl) serviceIdEl.value = String(serviceData.id || 0);
        if (serviceTitleEl) serviceTitleEl.value = String(serviceData.title || '');
        if (serviceCategoryEl) serviceCategoryEl.value = String(serviceData.category || '');
        if (servicePriceEl) servicePriceEl.value = String(serviceData.price || '');
        if (serviceDescriptionEl) serviceDescriptionEl.value = String(serviceData.description || '');
        if (serviceImageLabelEl && serviceData.image_path) {
          serviceImageLabelEl.textContent = 'Изображение: ' + String(serviceData.image_path).split('/').pop();
        }
      }

      serviceModalEl.style.display = 'block';
      const serviceModalDropdown = document.getElementById('serviceModalDropdown');
      if (serviceModalDropdown) serviceModalDropdown.style.display = 'flex';
    }

    function closeServiceEditor() {
      if (serviceModalEl) serviceModalEl.style.display = 'none';
      const serviceModalDropdown = document.getElementById('serviceModalDropdown');
      if (serviceModalDropdown) serviceModalDropdown.style.display = 'none';
    }

    function deleteServiceItem() {
      if (!serviceIdEl || serviceIdEl.value === '0') {
        alert('Сначала выберите существующую услугу для удаления.');
        return;
      }
      if (!confirm('Удалить услугу?')) return;

      const form = document.createElement('form');
      form.method = 'post';
      form.innerHTML = '<input type="hidden" name="service_action" value="delete_service">' +
        '<input type="hidden" name="service_id" value="' + serviceIdEl.value + '">';
      document.body.appendChild(form);
      form.submit();
    }

    if (serviceImageEl && serviceImageLabelEl) {
      serviceImageLabelEl.style.cursor = 'pointer';
      serviceImageLabelEl.addEventListener('click', () => serviceImageEl.click());
      serviceImageEl.addEventListener('change', function () {
        if (this.files && this.files[0]) {
          serviceImageLabelEl.textContent = 'Изображение: ' + this.files[0].name;
        }
      });
    }

  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
