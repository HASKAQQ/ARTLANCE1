<?php
require_once __DIR__ . '/inc_app.php';
$user = current_user();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (mb_strlen($name) < 2) $errors[] = 'Имя должно содержать минимум 2 символа.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Введите корректный email.';
    if (mb_strlen($message) < 10) $errors[] = 'Сообщение должно содержать минимум 10 символов.';

    if (!$errors) {
        execute_query('INSERT INTO contact_messages (name,email,message) VALUES (?,?,?)', 'sss', [$name, $email, $message]);
        $success = 'Спасибо! Ожидайте ответа на почте. Сообщение отправлено администратору: ' . ADMIN_EMAIL;
    }
}

$artists = fetch_all("SELECT id,name,bio,avatar,categories FROM users WHERE role='artist' ORDER BY id DESC LIMIT 3");
$isLogged = (bool)$user;
$ctaUrl = $isLogged ? 'artists.php' : 'login.php';
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ARTlance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head><body>
<?php include 'header.php'; ?>
<section class="banner position-relative"><div class="container py-5">
  <h1 class="banner-title">Найдите идеального художника за 5 минут</h1>
  <a class="btn btn-custom mt-3" href="<?= $ctaUrl; ?>">Найти</a>
</div></section>
<section class="about py-5" id="about"><div class="container"><h2>О проекте ARTlance</h2><p>Платформа для художников и заказчиков в сфере арт-услуг.</p></div></section>
<section class="artists py-5"><div class="container"><h2 class="artists-title mb-4">Новые художники</h2><div class="row g-4 mb-4">
<?php foreach ($artists as $artist): ?>
  <div class="col-lg-4 col-md-6"><a href="profile-artist.php?id=<?= (int)$artist['id']; ?>" class="text-decoration-none"><div class="artist-card p-3 bg-white rounded">
  <img src="<?= h($artist['avatar']); ?>" class="artist-avatar mb-2" alt="avatar"><h3 class="artist-name"><?= h($artist['name']); ?></h3>
  <p class="artist-specialty"><?= h($artist['categories'] ?: 'Без категории'); ?></p></div></a></div>
<?php endforeach; ?>
</div><div class="text-center"><a class="btn btn-load-more" href="<?= $ctaUrl; ?>">Смотреть еще</a></div></div></section>
<section class="contacts py-5" id="contact"><div class="container" style="max-width:700px;">
<h2>Обратная связь</h2>
<?php if ($success): ?><div class="alert alert-success"><?= h($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo '<div>'.h($e).'</div>'; ?></div><?php endif; ?>
<form method="post" class="contacts-form" novalidate>
<input type="hidden" name="contact_form" value="1">
<input type="text" name="name" placeholder="Имя" class="form-control mb-3" required>
<input type="email" name="email" placeholder="Электронная почта" class="form-control mb-3" required>
<textarea name="message" placeholder="Написать сообщение" class="form-control mb-3" rows="3" required></textarea>
<button type="submit" class="btn btn-custom">Отправить</button>
</form></div></section>
<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
