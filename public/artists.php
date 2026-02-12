<?php
require_once __DIR__ . '/inc_app.php';
$artists = fetch_all("SELECT id,name,bio,avatar,categories,registered_at FROM users WHERE role='artist' ORDER BY registered_at DESC");
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Художники</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="css/style.css"></head><body>
<?php include 'header.php'; ?>
<div class="container py-5"><h1 class="mb-4">Художники</h1><div class="row g-4">
<?php foreach ($artists as $artist): ?>
<div class="col-md-6 col-lg-4"><div class="card h-100"><img src="<?= h($artist['avatar']); ?>" class="card-img-top" style="height:220px;object-fit:cover;" alt="">
<div class="card-body"><h5><?= h($artist['name']); ?></h5><p><?= h($artist['bio'] ?: 'Без описания'); ?></p><p class="small text-muted"><?= h($artist['categories']); ?></p>
<a class="btn btn-dark" href="profile-artist.php?id=<?= (int)$artist['id']; ?>">Профиль</a></div></div></div>
<?php endforeach; ?>
</div></div>
<?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
