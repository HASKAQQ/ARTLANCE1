<?php
require_once __DIR__ . '/inc_app.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['switch_role'])) {
        $newRole = $_POST['new_role'] === 'artist' ? 'artist' : 'client';
        execute_query('UPDATE users SET role=? WHERE id=?', 'si', [$newRole, $user['id']]);
        redirect('profile-artist-edit.php');
    }

    if (isset($_POST['save_profile'])) {
        execute_query('UPDATE users SET name=?, bio=?, categories=?, avatar=? WHERE id=?', 'ssssi', [trim($_POST['name']), trim($_POST['bio']), trim($_POST['categories']), trim($_POST['avatar']), $user['id']]);
        redirect('profile-artist-edit.php');
    }

    if ($user['role'] === 'artist' && isset($_POST['add_service'])) {
        execute_query('INSERT INTO services (user_id,title,category,description,price,image) VALUES (?,?,?,?,?,?)', 'isssds', [$user['id'], trim($_POST['title']), trim($_POST['category']), trim($_POST['description']), (float)$_POST['price'], trim($_POST['image'])]);
        redirect('profile-artist-edit.php#services');
    }

    if ($user['role'] === 'artist' && isset($_POST['add_portfolio'])) {
        execute_query('INSERT INTO portfolio (user_id,title,image) VALUES (?,?,?)', 'iss', [$user['id'], trim($_POST['title']), trim($_POST['image'])]);
        redirect('profile-artist-edit.php#portfolio');
    }

    if (isset($_POST['order_status'])) {
        execute_query('UPDATE orders SET status=? WHERE id=? AND artist_id=?', 'sii', [$_POST['status'], (int)$_POST['order_id'], $user['id']]);
        redirect('profile-artist-edit.php#orders');
    }
}

$user = current_user();
$services = fetch_all('SELECT * FROM services WHERE user_id=? ORDER BY created_at DESC', 'i', [$user['id']]);
$portfolio = fetch_all('SELECT * FROM portfolio WHERE user_id=? ORDER BY created_at DESC', 'i', [$user['id']]);
$artistOrders = fetch_all('SELECT o.*, s.title FROM orders o JOIN services s ON s.id=o.service_id WHERE o.artist_id=? ORDER BY o.created_at DESC', 'i', [$user['id']]);
$clientOrders = fetch_all('SELECT o.*, s.title FROM orders o JOIN services s ON s.id=o.service_id WHERE o.client_id=? ORDER BY o.created_at DESC', 'i', [$user['id']]);
$reviews = fetch_all('SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.client_id WHERE artist_id=? ORDER BY r.created_at DESC', 'i', [$user['id']]);
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Профиль</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<?php include 'header.php'; ?>
<div class="container py-4"><h1>Мой профиль</h1>
<form method="post" class="mb-3 d-flex gap-2 align-items-center"><span>Режим:</span>
<input type="hidden" name="switch_role" value="1"><select name="new_role" class="form-select" style="max-width:220px"><option value="client" <?= $user['role']==='client'?'selected':''; ?>>Заказчик</option><option value="artist" <?= $user['role']==='artist'?'selected':''; ?>>Художник</option></select><button class="btn btn-dark">Переключить</button></form>

<div class="card p-3 mb-4"><h5>Основные данные</h5><form method="post" class="row g-2"><input type="hidden" name="save_profile" value="1">
<div class="col-md-6"><input class="form-control" name="name" value="<?= h($user['name']); ?>" placeholder="Имя"></div>
<div class="col-md-6"><input class="form-control" name="avatar" value="<?= h($user['avatar']); ?>" placeholder="Ссылка на аватар"></div>
<div class="col-12"><textarea class="form-control" name="bio" placeholder="О себе"><?= h($user['bio']); ?></textarea></div>
<div class="col-12"><input class="form-control" name="categories" value="<?= h($user['categories']); ?>" placeholder="Категории через запятую"></div>
<div class="col-12"><small>Дата регистрации: <?= h($user['registered_at']); ?></small></div><div class="col-12"><button class="btn btn-outline-dark">Сохранить</button></div></form></div>

<?php if ($user['role'] === 'client'): ?>
  <div class="card p-3"><h4>История заказов (заказчик)</h4><?php foreach ($clientOrders as $o): ?><div class="border rounded p-2 mb-2"><?= h($o['title']); ?> — <b><?= h($o['status']); ?></b></div><?php endforeach; ?></div>
<?php else: ?>
  <div class="card p-3 mb-3" id="orders"><h4>Заказы художника</h4><?php foreach ($artistOrders as $o): ?><form method="post" class="d-flex gap-2 mb-2"><input type="hidden" name="order_status" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id']; ?>"><span class="me-2"><?= h($o['title']); ?></span><select name="status" class="form-select form-select-sm" style="max-width:220px"><option <?= $o['status']==='Новый'?'selected':''; ?>>Новый</option><option <?= $o['status']==='В работе'?'selected':''; ?>>В работе</option><option <?= $o['status']==='Готово'?'selected':''; ?>>Готово</option></select><button class="btn btn-sm btn-dark">Сменить статус</button></form><?php endforeach; ?></div>
  <div class="card p-3 mb-3" id="portfolio"><h4>Портфолио</h4><form method="post" class="row g-2 mb-3"><input type="hidden" name="add_portfolio" value="1"><div class="col-md-4"><input class="form-control" name="title" placeholder="Название" required></div><div class="col-md-6"><input class="form-control" name="image" placeholder="Путь к изображению" required></div><div class="col-md-2"><button class="btn btn-dark w-100">+</button></div></form><?php foreach ($portfolio as $p): ?><div><?= h($p['title']); ?></div><?php endforeach; ?></div>
  <div class="card p-3 mb-3" id="services"><h4>Услуги</h4><form method="post" class="row g-2 mb-3"><input type="hidden" name="add_service" value="1"><div class="col-md-4"><input class="form-control" name="title" placeholder="Название" required></div><div class="col-md-3"><input class="form-control" name="category" placeholder="Категория" required></div><div class="col-md-2"><input class="form-control" type="number" name="price" placeholder="Цена" required></div><div class="col-md-3"><input class="form-control" name="image" value="src/image/Rectangle 55.png"></div><div class="col-12"><textarea class="form-control" name="description" placeholder="Описание" required></textarea></div><div class="col-12"><button class="btn btn-dark">Добавить услугу</button></div></form><?php foreach ($services as $s): ?><div class="border p-2 mb-2"><?= h($s['title']); ?> (<?= h($s['category']); ?>)</div><?php endforeach; ?></div>
  <div class="card p-3" id="reviews"><h4>Отзывы</h4><?php foreach ($reviews as $r): ?><div class="border rounded p-2 mb-2"><b><?= h($r['name']); ?>:</b> <?= h($r['text']); ?></div><?php endforeach; ?></div>
<?php endif; ?>
</div><?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
