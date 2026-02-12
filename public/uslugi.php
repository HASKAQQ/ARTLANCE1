<?php
require_once __DIR__ . '/inc_app.php';
$category = trim($_GET['category'] ?? 'Все');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 3;
$offset = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];
$types = '';
if ($category !== 'Все') { $where .= ' AND s.category = ? '; $types .= 's'; $params[] = $category; }
if ($search !== '') { $where .= ' AND s.category LIKE ? '; $types .= 's'; $params[] = "%$search%"; }

$services = fetch_all("SELECT s.*, u.name artist_name, u.avatar artist_avatar FROM services s JOIN users u ON u.id=s.user_id $where ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset", $types, $params);
$total = fetch_one("SELECT COUNT(*) c FROM services s $where", $types, $params)['c'] ?? 0;
$hasMore = $total > ($offset + $perPage);
$categories = ['Все','Графический дизайн','Иллюстрация','Цифровая живопись','3D-моделирование','Живопись и графика'];
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Услуги</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="css/style.css"></head><body>
<?php include 'header.php'; ?>
<div class="container py-5"><h1 class="mb-4">Услуги</h1><div class="d-flex flex-wrap gap-2 mb-3">
<?php foreach ($categories as $cat): ?><a class="btn btn-sm <?= $category===$cat?'btn-dark':'btn-outline-dark'; ?>" href="?category=<?= urlencode($cat); ?>"><?= h($cat); ?></a><?php endforeach; ?>
<form class="d-flex ms-auto" method="get"><input type="hidden" name="category" value="Все"><input class="form-control form-control-sm me-2" name="search" placeholder="Прочее: введите категорию"><button class="btn btn-sm btn-secondary">Найти</button></form>
</div>
<div class="row g-4"><?php foreach ($services as $service): ?><div class="col-12"><div class="card"><div class="row g-0"><div class="col-md-4"><img src="<?= h($service['image']); ?>" class="img-fluid rounded-start" style="height:100%;object-fit:cover;"></div><div class="col-md-8"><div class="card-body"><h5><?= h($service['title']); ?></h5><p><?= h($service['description']); ?></p><span class="badge bg-secondary"><?= h($service['category']); ?></span><p class="mt-2 mb-1 fw-bold"><?= (int)$service['price']; ?> ₽</p><a href="order.php?id=<?= (int)$service['id']; ?>" class="btn btn-dark btn-sm">Открыть</a></div></div></div></div></div><?php endforeach; ?></div>
<?php if ($hasMore): ?><div class="text-center mt-4"><a class="btn btn-load-more" href="?category=<?= urlencode($category); ?>&search=<?= urlencode($search); ?>&page=<?= $page+1; ?>">Смотреть еще</a></div><?php endif; ?>
</div>
<?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
