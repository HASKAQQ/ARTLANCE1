// Load header and footer
function loadComponent(url, elementId) {
  if (document.getElementById(elementId)) {
    fetch(url)
      .then(response => response.text())
      .then(data => {
        document.getElementById(elementId).innerHTML = data;
      })
      .catch(error => console.error(`Ошибка загрузки ${url}:`, error));
  }
}

// Phone form submission
const phoneForm = document.getElementById('phoneForm');
if (phoneForm) {
  phoneForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const phoneInput = document.getElementById('phoneInput');
    const phone = phoneInput.value;

    // Отправляем запрос на сервер
    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Показываем код в консоли (для демонстрации)
        console.log('=================================');
        console.log('ВАШ КОД ПОДТВЕРЖДЕНИЯ:', data.code);
        console.log('=================================');
        alert('Код отправлен! Проверьте консоль браузера (F12)');

        // Переключаемся на форму ввода кода
        const phoneStep = document.getElementById('phoneStep');
        const codeStep = document.getElementById('codeStep');
        const code1 = document.getElementById('code1');

        if (phoneStep) phoneStep.classList.add('d-none');
        if (codeStep) codeStep.classList.remove('d-none');
        if (code1) code1.focus();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
  });
}

// Code input auto-focus
const codeInputs = document.querySelectorAll('.code-input');
if (codeInputs.length > 0) {
  codeInputs.forEach((input, index) => {
    input.addEventListener('input', function () {
      if (this.value.length === 1) {
        if (index < codeInputs.length - 1) {
          const nextInput = codeInputs[index + 1];
          if (nextInput) nextInput.focus();
        } else {
          // Последняя цифра введена - автоматически проверяем код
          verifyCode();
        }
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && this.value === '' && index > 0) {
        const prevInput = codeInputs[index - 1];
        if (prevInput) prevInput.focus();
      }
    });
  });
}

// Функция проверки кода
function verifyCode() {
  const code = Array.from(codeInputs).map(input => input.value).join('');

  if (code.length === 5) {
    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=verify_code&code=${encodeURIComponent(code)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Успешный вход - переходим в профиль
        window.location.href = data.redirect;
      } else {
        alert(data.message || 'Неверный код');
        // Очищаем поля
        codeInputs.forEach(input => input.value = '');
        if (codeInputs[0]) codeInputs[0].focus();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
  }
}

// Resend code button
const resendBtn = document.getElementById('resendBtn');
if (resendBtn && codeInputs.length > 0) {
  resendBtn.addEventListener('click', function () {
    const phoneInput = document.getElementById('phoneInput');
    const phone = phoneInput.value;

    // Повторно запрашиваем код
    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('=================================');
        console.log('НОВЫЙ КОД ПОДТВЕРЖДЕНИЯ:', data.code);
        console.log('=================================');
        alert('Новый код отправлен! Проверьте консоль браузера (F12)');
        codeInputs.forEach(input => input.value = '');
        if (codeInputs[0]) codeInputs[0].focus();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
  });
}

function toggleSection(sectionName) {
  const content = document.getElementById(sectionName + 'Content');
  const arrow = document.getElementById(sectionName + 'Arrow');

  if (content && arrow) {
    if (content.style.display === 'none') {
      content.style.display = 'block';
      arrow.textContent = '▼';
    } else {
      content.style.display = 'none';
      arrow.textContent = '▶';
    }
  }
}

// Функции для модальных окон
function openPortfolioModal(card) {
  const modal = document.getElementById('portfolioModal');
  const dropdown = document.getElementById('portfolioModalDropdown');
  if (modal) modal.style.display = 'flex';
  if (dropdown) dropdown.style.display = 'flex';
}

function openServiceModal(card) {
  const modal = document.getElementById('serviceModal');
  const dropdown = document.getElementById('serviceModalDropdown');
  if (modal) modal.style.display = 'flex';
  if (dropdown) dropdown.style.display = 'flex';
}

function closeModalOnOverlay(event, modalId) {
  if (event.target.classList.contains('modal-overlay')) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
  }
}

function savePortfolio() {
  alert('Портфолио сохранено!');
  const modal = document.getElementById('portfolioModal');
  const dropdown = document.getElementById('portfolioModalDropdown');
  if (modal) modal.style.display = 'none';
  if (dropdown) dropdown.style.display = 'none';
}

function deletePortfolio() {
  if (confirm('Удалить работу из портфолио?')) {
    alert('Работа удалена!');
    const modal = document.getElementById('portfolioModal');
    const dropdown = document.getElementById('portfolioModalDropdown');
    if (modal) modal.style.display = 'none';
    if (dropdown) dropdown.style.display = 'none';
  }
}

function saveService() {
  alert('Услуга сохранена!');
  const modal = document.getElementById('serviceModal');
  const dropdown = document.getElementById('serviceModalDropdown');
  if (modal) modal.style.display = 'none';
  if (dropdown) dropdown.style.display = 'none';
}

function deleteService() {
  if (confirm('Удалить услугу?')) {
    alert('Услуга удалена!');
    const modal = document.getElementById('serviceModal');
    const dropdown = document.getElementById('serviceModalDropdown');
    if (modal) modal.style.display = 'none';
    if (dropdown) dropdown.style.display = 'none';
  }
}

// Переключение роли
const roleButtons = document.querySelectorAll('.role-btn');
if (roleButtons.length > 0) {
  roleButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      roleButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });
}

// FAQ аккордеон
let questions = document.querySelectorAll('.question');
let answers = document.querySelectorAll('.answer');

if (questions.length > 0 && answers.length > 0) {
  questions.forEach((question, index) => {
    question.onclick = () => {
      questions[index].classList.toggle('active');
      answers[index].classList.toggle('active');
    };
  });
}

let menubtn = document.querySelector('.menu-btn');

if (menubtn) {
  menubtn.onclick = () => {
    document.querySelector('.nav-menu').classList.toggle('active');
  };
}

let ids = document.querySelectorAll('.id');
let admIdMenus = document.querySelectorAll('.adm-id-menu');

if (ids.length > 0 && admIdMenus.length > 0) {
  ids.forEach((id, index) => {
    id.onclick = () => {
      ids[index].classList.toggle('active');
      admIdMenus[index].classList.toggle('active');
    };
  });
}

async function fetchCategoryPayload(action, payload = {}) {
  const params = new URLSearchParams({ action, ...payload });
  const response = await fetch('profile-artist-edit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params.toString()
  });

  return response.json();
}

function renderProfileCategories(categories) {
  const tagsContainer = document.getElementById('profileTagsContainer');
  if (!tagsContainer) return;

  const addButton = tagsContainer.querySelector('.profile-tag-add');
  tagsContainer.querySelectorAll('.profile-tag-item').forEach(item => item.remove());

  categories.forEach(category => {
    const item = document.createElement('div');
    item.className = 'profile-tag-item';
    item.dataset.categoryId = category.id;
    item.innerHTML = `<p class="profile-tag">${category.name}</p>
      <button type="button" class="profile-tag-remove" onclick="removeProfileCategory(${category.id})">×</button>`;

    tagsContainer.insertBefore(item, addButton);
  });
}

async function openCategoryModal() {
  const modal = document.getElementById('categoryModal');
  const select = document.getElementById('existingCategorySelect');
  if (!modal || !select) return;

  const data = await fetchCategoryPayload('list_categories');
  if (!data.success) {
    alert(data.message || 'Не удалось загрузить категории');
    return;
  }

  select.innerHTML = '<option value="">Выберите существующую категорию</option>';
  data.categories.forEach(category => {
    const option = document.createElement('option');
    option.value = category.id;
    option.textContent = category.name;
    select.appendChild(option);
  });

  modal.style.display = 'flex';
}

function closeCategoryModal() {
  const modal = document.getElementById('categoryModal');
  const customInput = document.getElementById('customCategoryInput');
  const select = document.getElementById('existingCategorySelect');

  if (modal) modal.style.display = 'none';
  if (customInput) customInput.value = '';
  if (select) select.value = '';
}

async function saveProfileCategory() {
  const customInput = document.getElementById('customCategoryInput');
  const select = document.getElementById('existingCategorySelect');
  if (!customInput || !select) return;

  const categoryName = customInput.value.trim();
  const categoryId = select.value;

  if (!categoryName && !categoryId) {
    alert('Выберите категорию или введите новую.');
    return;
  }

  const data = await fetchCategoryPayload('add_category', {
    category_name: categoryName,
    category_id: categoryId
  });

  if (!data.success) {
    alert(data.message || 'Не удалось сохранить категорию');
    return;
  }

  renderProfileCategories(data.profile_categories || []);
  closeCategoryModal();
}

async function removeProfileCategory(categoryId) {
  const data = await fetchCategoryPayload('remove_category', { category_id: String(categoryId) });
  if (!data.success) {
    alert(data.message || 'Не удалось удалить категорию');
    return;
  }

  renderProfileCategories(data.profile_categories || []);
}
