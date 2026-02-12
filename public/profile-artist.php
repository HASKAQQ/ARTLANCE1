<?php
require_once __DIR__ . '/inc_app.php';
$id = (int)($_GET['id'] ?? 0);
$artist = fetch_one("SELECT * FROM users WHERE id=? AND role='artist'", 'i', [$id]);
if (!$artist) redirect('artists.php');
$services = fetch_all('SELECT * FROM services WHERE user_id=? ORDER BY created_at DESC', 'i', [$id]);
$portfolio = fetch_all('SELECT * FROM portfolio WHERE user_id=? ORDER BY created_at DESC', 'i', [$id]);
$reviews = fetch_all('SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.client_id WHERE artist_id=? ORDER BY r.created_at DESC', 'i', [$id]);
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Профиль художника</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<?php include 'header.php'; ?><div class="container py-5"><div class="d-flex align-items-center gap-3 mb-3"><img src="<?= h($artist['avatar']); ?>" style="width:80px;height:80px;border-radius:50%"><div><h1><?= h($artist['name']); ?></h1><div><?= h($artist['bio']); ?></div></div></div>
<p><b>Категории:</b> <?= h($artist['categories']); ?></p><p><b>Дата регистрации:</b> <?= h($artist['registered_at']); ?></p>
<h3>Услуги</h3><?php foreach ($services as $s): ?><div class="border rounded p-2 mb-2"><a href="order.php?id=<?= (int)$s['id']; ?>"><?= h($s['title']); ?></a></div><?php endforeach; ?>
<h3>Портфолио</h3><?php foreach ($portfolio as $p): ?><div class="mb-2"><?= h($p['title']); ?></div><?php endforeach; ?>
<h3>Отзывы</h3><?php foreach ($reviews as $r): ?><div class="border rounded p-2 mb-2"><b><?= h($r['name']); ?></b>: <?= h($r['text']); ?></div><?php endforeach; ?></div>
<?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
