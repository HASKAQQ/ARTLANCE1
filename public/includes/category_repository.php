<?php

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../data/app.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        display_name TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_by_user_id INTEGER NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(name COLLATE NOCASE)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS profile_categories (
        profile_user_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        PRIMARY KEY (profile_user_id, category_id)
    )');

    $countUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($countUsers === 0) {
        $pdo->exec("INSERT INTO users (display_name) VALUES ('Екатерина Кравчюк')");
    }

    $countCategories = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($countCategories === 0) {
        $seed = [
            '3D-моделирование и визуализация',
            'Графический дизайн',
            'Цифровая живопись',
        ];

        $stmt = $pdo->prepare('INSERT INTO categories (name, created_by_user_id) VALUES (:name, :created_by_user_id)');
        foreach ($seed as $name) {
            $stmt->execute([
                ':name' => $name,
                ':created_by_user_id' => 1,
            ]);
        }

        $pdo->exec('INSERT INTO profile_categories (profile_user_id, category_id)
            SELECT 1, id FROM categories');
    }
}

function getUserProfileCategories(int $userId): array
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT c.id, c.name
        FROM profile_categories pc
        INNER JOIN categories c ON c.id = pc.category_id
        WHERE pc.profile_user_id = :profile_user_id
        ORDER BY c.name');
    $stmt->execute([':profile_user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCategories(): array
{
    $pdo = getDbConnection();

    $stmt = $pdo->query('SELECT c.id, c.name, c.created_by_user_id, u.display_name AS created_by_name
        FROM categories c
        LEFT JOIN users u ON u.id = c.created_by_user_id
        ORDER BY c.name');

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createCategory(string $name, ?int $createdByUserId): int
{
    $pdo = getDbConnection();
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new InvalidArgumentException('Название категории не может быть пустым.');
    }

    $checkStmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name COLLATE NOCASE');
    $checkStmt->execute([':name' => $normalizedName]);
    $existingId = $checkStmt->fetchColumn();

    if ($existingId !== false) {
        return (int) $existingId;
    }

    $stmt = $pdo->prepare('INSERT INTO categories (name, created_by_user_id) VALUES (:name, :created_by_user_id)');
    $stmt->execute([
        ':name' => $normalizedName,
        ':created_by_user_id' => $createdByUserId,
    ]);

    return (int) $pdo->lastInsertId();
}

function addCategoryToProfile(int $userId, int $categoryId): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO profile_categories (profile_user_id, category_id)
        VALUES (:profile_user_id, :category_id)');
    $stmt->execute([
        ':profile_user_id' => $userId,
        ':category_id' => $categoryId,
    ]);
}

function removeCategoryFromProfile(int $userId, int $categoryId): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('DELETE FROM profile_categories WHERE profile_user_id = :profile_user_id
        AND category_id = :category_id');
    $stmt->execute([
        ':profile_user_id' => $userId,
        ':category_id' => $categoryId,
    ]);
}

function updateCategoryName(int $categoryId, string $name): void
{
    $pdo = getDbConnection();
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new InvalidArgumentException('Название категории не может быть пустым.');
    }

    $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
    $stmt->execute([
        ':name' => $normalizedName,
        ':id' => $categoryId,
    ]);
}

function deleteCategory(int $categoryId): void
{
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $stmtProfile = $pdo->prepare('DELETE FROM profile_categories WHERE category_id = :category_id');
    $stmtProfile->execute([':category_id' => $categoryId]);

    $stmtCategory = $pdo->prepare('DELETE FROM categories WHERE id = :category_id');
    $stmtCategory->execute([':category_id' => $categoryId]);

    $pdo->commit();
}
