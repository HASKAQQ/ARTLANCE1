<?php require_once __DIR__ . '/inc_app.php'; ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Вопрос-ответ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<?php include 'header.php'; ?><div class="container py-5" style="max-width:900px;"><h1 class="mb-4">Вопросы и ответы</h1>
<div class="accordion" id="faq">
<?php
$faq = [
['Как оформить заказ?','Выберите услугу, откройте карточку и нажмите «Купить». Заказ появится в истории.'],
['Как стать художником?','В профиле переключите роль на «Художник», заполните категории и добавьте услугу.'],
['Как сменить аватар?','В профиле укажите путь к картинке в поле «Ссылка на аватар».'],
['Как работает оплата?','Кошелек и оплата сейчас в статусе «В разработке».'],
['Куда обратиться за поддержкой?','Напишите в форму «Обратная связь» на главной странице.']
];
foreach ($faq as $i => $row): ?>
<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?= $i?'collapsed':''; ?>" data-bs-toggle="collapse" data-bs-target="#q<?= $i; ?>"><?= h($row[0]); ?></button></h2><div id="q<?= $i; ?>" class="accordion-collapse collapse <?= !$i?'show':''; ?>" data-bs-parent="#faq"><div class="accordion-body"><?= h($row[1]); ?></div></div></div>
<?php endforeach; ?>
</div></div><?php include 'footer.php'; ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
