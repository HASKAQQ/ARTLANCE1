<?php
session_start();

const DB_HOST = 'MySQL-8.0';
const DB_NAME = 'artlance';
const DB_USER = 'root';
const DB_PASS = '';
const LOGIN_PHONE = '+79930170672';
const ADMIN_PHONE = '+79930170000';
const ADMIN_EMAIL = 'chudova0908@gmail.com';

function db(): ?mysqli {
    static $db = null;
    if ($db instanceof mysqli) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_errno) {
        return null;
    }
    $db->set_charset('utf8mb4');
    init_schema($db);
    return $db;
}

function init_schema(mysqli $db): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(120) DEFAULT 'Новый пользователь',
            email VARCHAR(120) DEFAULT '',
            role ENUM('client','artist','admin') DEFAULT 'client',
            bio TEXT,
            categories TEXT,
            avatar VARCHAR(255) DEFAULT 'src/image/Ellipse 2.png',
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            image VARCHAR(255) DEFAULT 'src/image/Rectangle 55.png',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS portfolio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            image VARCHAR(255) DEFAULT 'src/image/Rectangle 76.png',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            client_id INT NOT NULL,
            artist_id INT NOT NULL,
            status VARCHAR(60) DEFAULT 'Новый',
            payment_method VARCHAR(60) DEFAULT 'Карта',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (artist_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL,
            service_id INT NOT NULL,
            client_id INT NOT NULL,
            artist_id INT NOT NULL,
            text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(60) DEFAULT 'Карта',
            status VARCHAR(60) DEFAULT 'В разработке',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $query) {
        $db->query($query);
    }

    seed_data($db);
    $initialized = true;
}

function seed_data(mysqli $db): void {
    $count = (int)($db->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'] ?? 0);
    if ($count > 0) {
        return;
    }

    $db->query("INSERT INTO users (phone,name,role,bio,categories,avatar) VALUES
        ('+79930170672','Алина Заказчик','client','Ищу художников для творческих задач','Иллюстрация, Графический дизайн','src/image/Ellipse 2.png'),
        ('+79930170000','Администратор','admin','Управление платформой','Управление','src/image/Ellipse 2.png'),
        ('+79931111111','Екатерина Кравчюк','artist','3D художник и концепт-артист','3D-моделирование, Иллюстрация','src/image/Ellipse 3.png'),
        ('+79932222222','Марина Рафт','artist','Иллюстратор детских книг','Иллюстрация, Живопись','src/image/Ellipse 4.png')");

    $db->query("INSERT INTO services (user_id,title,category,description,price,image) VALUES
        (3,'3D персонаж','3D-моделирование','Создам 3D-персонажа по вашему ТЗ',8000,'src/image/Rectangle 55.png'),
        (4,'Иллюстрация для книги','Иллюстрация','Сделаю обложку и 2 разворота',6500,'src/image/Rectangle 76.png')");
}

function fetch_all(string $sql, string $types = '', array $params = []): array {
    $db = db();
    if (!$db) return [];

    if (!$types) {
        $result = $db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_one(string $sql, string $types = '', array $params = []): ?array {
    $rows = fetch_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function execute_query(string $sql, string $types = '', array $params = []): bool {
    $db = db();
    if (!$db) return false;
    if (!$types) return (bool)$db->query($sql);
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return fetch_one('SELECT * FROM users WHERE id = ?', 'i', [(int)$_SESSION['user_id']]);
}

function require_login(): void {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function h(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}
