<?php require_once __DIR__ . '/inc_app.php'; require_login(); if (!is_admin()) redirect('index.php');
$stats = [
'users' => fetch_one('SELECT COUNT(*) c FROM users')['c'] ?? 0,
'services' => fetch_one('SELECT COUNT(*) c FROM services')['c'] ?? 0,
'orders' => fetch_one('SELECT COUNT(*) c FROM orders')['c'] ?? 0,
'transactions' => fetch_one('SELECT COUNT(*) c FROM transactions')['c'] ?? 0,
];
$messages = fetch_all('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 10');
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Админ</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<?php include 'admin_header.php'; ?><div class="container py-4"><h1>Панель администратора</h1><div class="row g-3 mb-4"><?php foreach($stats as $k=>$v): ?><div class="col-md-3"><div class="card p-3"><b><?= h($k); ?></b><div class="fs-4"><?= (int)$v; ?></div></div></div><?php endforeach; ?></div>
<div class="mb-3 d-flex gap-2"><a class="btn btn-outline-dark" href="admin-users.php">Пользователи</a><a class="btn btn-outline-dark" href="admin-services.php">Услуги</a><a class="btn btn-outline-dark" href="admin-orders.php">Заказы</a><a class="btn btn-outline-dark" href="admin-transactions.php">Транзакции</a></div>
<h3>Обратная связь</h3><table class="table table-striped"><tr><th>Имя</th><th>Email</th><th>Сообщение</th><th>Дата</th></tr><?php foreach($messages as $m): ?><tr><td><?= h($m['name']); ?></td><td><?= h($m['email']); ?></td><td><?= h($m['message']); ?></td><td><?= h($m['created_at']); ?></td></tr><?php endforeach; ?></table>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
