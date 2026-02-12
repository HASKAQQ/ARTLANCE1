<?php $user = current_user(); ?>
<header class="header border-bottom border-4" id="header">
  <nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container">
      <a href="index.php" class="logo navbar-brand text-decoration-none">ARTlance</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mx-auto">
          <li class="nav-item"><a class="nav-link fw-semibold text-dark" href="index.php#about">О проекте</a></li>
          <li class="nav-item"><a class="nav-link fw-semibold text-dark" href="uslugi.php">Услуги</a></li>
          <li class="nav-item"><a class="nav-link fw-semibold text-dark" href="artists.php">Художники</a></li>
        </ul>
        <?php if ($user): ?>
          <div class="dropdown">
            <a href="#" class="d-inline-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
              <img src="<?= h($user['avatar']); ?>" alt="avatar" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($user['role'] === 'admin'): ?>
                <li><a class="dropdown-item" href="admin-main.php">Админ-панель</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="profile-artist-edit.php">Профиль</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="logout.php">Выход</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="login.php" class="login text-decoration-none d-none d-lg-block">Вход</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
