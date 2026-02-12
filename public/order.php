<?php
require_once __DIR__ . '/inc_app.php';
$serviceId = (int)($_GET['id'] ?? 0);
$service = fetch_one('SELECT s.*,u.id artist_id,u.name artist_name,u.avatar,u.bio FROM services s JOIN users u ON u.id=s.user_id WHERE s.id=?', 'i', [$serviceId]);
if (!$service) redirect('uslugi.php');
$user = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['buy'])) {
    if (!$user) redirect('login.php');
    execute_query('INSERT INTO orders (service_id,client_id,artist_id,status,payment_method) VALUES (?,?,?,?,?)', 'iiiss', [$serviceId, $user['id'], $service['artist_id'], 'В разработке', $_POST['payment'] ?? 'Карта']);
    $orderId = db()->insert_id;
    execute_query('INSERT INTO transactions (order_id,amount,method,status) VALUES (?,?,?,?)', 'idss', [$orderId, $service['price'], $_POST['payment'] ?? 'Карта', 'В разработке']);
    $message = 'Кошелек в разработке. Заказ создан в системе.';
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['review']) && $user) {
    execute_query('INSERT INTO reviews (service_id,client_id,artist_id,text) VALUES (?,?,?,?)', 'iiis', [$serviceId, $user['id'], $service['artist_id'], trim($_POST['review_text'])]);
}
$reviews = fetch_all('SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.client_id WHERE r.service_id=? ORDER BY r.created_at DESC', 'i', [$serviceId]);
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Услуга</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<?php include 'header.php'; ?><div class="container py-5"><div class="row g-4"><div class="col-lg-7"><img src="<?= h($service['image']); ?>" class="img-fluid rounded mb-3"><h1><?= h($service['title']); ?></h1><p><b>Категория:</b> <?= h($service['category']); ?></p><p><?= h($service['description']); ?></p></div>
<div class="col-lg-5"><div class="card p-3"><h4>Купить услугу</h4><?php if (!empty($message)): ?><div class="alert alert-info"><?= h($message); ?></div><?php endif; ?><p class="fw-bold"><?= (int)$service['price']; ?> ₽</p>
<form method="post"><select class="form-select mb-2" name="payment"><option>Карта</option><option>СБП</option></select><button class="btn btn-dark w-100" name="buy">Купить (в разработке)</button></form><hr>
<div class="d-flex align-items-center gap-2"><img src="<?= h($service['avatar']); ?>" style="width:50px;height:50px;border-radius:50%"><div><div><?= h($service['artist_name']); ?></div><small><?= h($service['bio']); ?></small></div></div></div></div></div>
<div class="mt-4"><h3>Отзывы</h3><?php if ($user): ?><form method="post" class="mb-3"><textarea name="review_text" class="form-control mb-2" required placeholder="Оставить отзыв"></textarea><button class="btn btn-outline-dark" name="review">Отправить</button></form><?php endif; ?>
<?php foreach ($reviews as $r): ?><div class="border rounded p-2 mb-2"><b><?= h($r['name']); ?>:</b> <?= h($r['text']); ?></div><?php endforeach; ?></div></div>
<?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
