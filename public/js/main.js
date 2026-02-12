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

function showInlineMessage(elementId, text, isError = false) {
  const messageElement = document.getElementById(elementId);
  if (!messageElement) return;

  messageElement.textContent = text;
  messageElement.classList.toggle('error', isError);
  messageElement.classList.toggle('success', !isError && text.length > 0);
}

function normalizePhoneInput(rawPhone) {
  const digits = rawPhone.replace(/\D/g, '');
  if (digits.length === 11 && digits.startsWith('8')) {
    return `+7${digits.slice(1)}`;
  }
  if (digits.length === 11 && digits.startsWith('7')) {
    return `+${digits}`;
  }
  return rawPhone.trim();
}

let currentLoginPhone = '';

// Phone form submission
const phoneForm = document.getElementById('phoneForm');
if (phoneForm) {
  phoneForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const phoneInput = document.getElementById('phoneInput');
    const submitButton = phoneForm.querySelector('button[type="submit"]');

    if (!phoneInput) return;

    const phone = normalizePhoneInput(phoneInput.value);
    phoneInput.value = phone;

    if (!/^\+7\d{10}$/.test(phone)) {
      showInlineMessage('loginMessage', 'Введите номер в формате +7XXXXXXXXXX', true);
      return;
    }

    showInlineMessage('loginMessage', '');
    if (submitButton) submitButton.disabled = true;

    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          showInlineMessage('loginMessage', data.message || 'Не удалось отправить код', true);
          return;
        }

        currentLoginPhone = phone;
        showInlineMessage('loginMessage', data.message || 'Код отправлен', false);

        if (data.debug_code) {
          console.log('=================================');
          console.log('ДЕМО-КОД ПОДТВЕРЖДЕНИЯ:', data.debug_code);
          console.log('=================================');
        }

        const phoneStep = document.getElementById('phoneStep');
        const codeStep = document.getElementById('codeStep');
        const code1 = document.getElementById('code1');

        if (phoneStep) phoneStep.classList.add('d-none');
        if (codeStep) codeStep.classList.remove('d-none');
        showInlineMessage('codeMessage', 'Код отправлен. Введите 5 цифр.', false);
        if (code1) code1.focus();
      })
      .catch(error => {
        console.error('Ошибка:', error);
        showInlineMessage('loginMessage', 'Ошибка соединения. Попробуйте еще раз.', true);
      })
      .finally(() => {
        if (submitButton) submitButton.disabled = false;
      });
  });
}

// Code input auto-focus
const codeInputs = document.querySelectorAll('.code-input');
if (codeInputs.length > 0) {
  codeInputs.forEach((input, index) => {
    input.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '');

      if (this.value.length === 1) {
        if (index < codeInputs.length - 1) {
          const nextInput = codeInputs[index + 1];
          if (nextInput) nextInput.focus();
        } else {
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

function clearCodeInputs() {
  codeInputs.forEach(input => {
    input.value = '';
  });
  if (codeInputs[0]) codeInputs[0].focus();
}

// Проверка кода
function verifyCode() {
  const code = Array.from(codeInputs).map(input => input.value).join('');

  if (!/^\d{5}$/.test(code)) {
    showInlineMessage('codeMessage', 'Введите 5 цифр кода.', true);
    return;
  }

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
        window.location.href = data.redirect;
      } else {
        showInlineMessage('codeMessage', data.message || 'Неверный код', true);
        clearCodeInputs();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      showInlineMessage('codeMessage', 'Ошибка соединения. Попробуйте снова.', true);
      clearCodeInputs();
    });
}

// Resend code button
const resendBtn = document.getElementById('resendBtn');
if (resendBtn && codeInputs.length > 0) {
  resendBtn.addEventListener('click', function () {
    if (!currentLoginPhone) {
      showInlineMessage('codeMessage', 'Сначала введите номер телефона.', true);
      return;
    }

    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(currentLoginPhone)}`
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          showInlineMessage('codeMessage', data.message || 'Не удалось отправить код', true);
          return;
        }

        if (data.debug_code) {
          console.log('=================================');
          console.log('ДЕМО-КОД ПОДТВЕРЖДЕНИЯ (НОВЫЙ):', data.debug_code);
          console.log('=================================');
        }

        showInlineMessage('codeMessage', data.message || 'Новый код отправлен', false);
        clearCodeInputs();
      })
      .catch(error => {
        console.error('Ошибка:', error);
        showInlineMessage('codeMessage', 'Ошибка соединения. Попробуйте снова.', true);
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
function openPortfolioModal() {
  const modal = document.getElementById('portfolioModal');
  const dropdown = document.getElementById('portfolioModalDropdown');
  if (modal) modal.style.display = 'flex';
  if (dropdown) dropdown.style.display = 'flex';
}

function openServiceModal() {
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
  const titleInput = document.getElementById('portfolioTitleInput');
  const portfolioGrid = document.getElementById('portfolioGrid');
  const title = titleInput ? titleInput.value.trim() : '';

  if (!title) {
    alert('Введите название работы.');
    return;
  }

  if (portfolioGrid) {
    const newCardCol = document.createElement('div');
    newCardCol.className = 'col-4 col-lg-3';
    newCardCol.innerHTML = `
      <div class="portfolio-card" onclick="openPortfolioModal(this)">
        <img src="src/image/Rectangle 55.png" alt="${title}" class="portfolio-image">
      </div>
      <p class="mt-2 mb-0 fw-semibold">${title}</p>
    `;

    const addCard = portfolioGrid.querySelector('.add-card')?.closest('.col-4');
    if (addCard) {
      portfolioGrid.insertBefore(newCardCol, addCard);
    } else {
      portfolioGrid.appendChild(newCardCol);
    }
  }

  if (titleInput) titleInput.value = '';
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
  const titleInput = document.getElementById('serviceTitleInput');
  const categoryInput = document.getElementById('serviceCategoryInput');
  const priceInput = document.getElementById('servicePriceInput');
  const servicesGrid = document.getElementById('servicesGrid');

  const title = titleInput ? titleInput.value.trim() : '';
  const category = categoryInput ? categoryInput.value.trim() : '';
  const price = priceInput ? priceInput.value.trim() : '';

  if (!title || !category || !price) {
    alert('Заполните название, категорию и цену.');
    return;
  }

  if (servicesGrid) {
    const newServiceCol = document.createElement('div');
    newServiceCol.className = 'col-6 col-lg-4';
    newServiceCol.innerHTML = `
      <div class="service-item card h-100" onclick="openServiceModal(this)">
        <img src="src/image/Rectangle 76.png" alt="Service" class="service-image">
        <div class="service-info">
          <h3 class="service-title">${title}</h3>
          <p class="service-category">${category}</p>
          <div class="service-bottom">
            <p class="service-price">от ${price}р</p>
            <p class="service-time">только что</p>
          </div>
        </div>
      </div>
    `;

    const addCard = servicesGrid.querySelector('.add-card')?.closest('.col-6');
    if (addCard) {
      servicesGrid.insertBefore(newServiceCol, addCard);
    } else {
      servicesGrid.appendChild(newServiceCol);
    }
  }

  if (titleInput) titleInput.value = '';
  if (categoryInput) categoryInput.value = '';
  if (priceInput) priceInput.value = '';

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

function showWaitModal() {
  const waitModal = document.getElementById('waitModal');
  if (waitModal) {
    waitModal.style.display = 'flex';
  } else {
    alert('Ожидайте...');
  }
}

function hideWaitModal() {
  const waitModal = document.getElementById('waitModal');
  if (waitModal) {
    waitModal.style.display = 'none';
  }
}

// Обработчики для "псевдо-платежей"
document.querySelectorAll('.js-wait-action').forEach(button => {
  button.addEventListener('click', function (e) {
    e.preventDefault();
    showWaitModal();
  });
});

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
const questions = document.querySelectorAll('.question');
const answers = document.querySelectorAll('.answer');

if (questions.length > 0 && answers.length > 0) {
  questions.forEach((question, index) => {
    question.onclick = () => {
      questions[index].classList.toggle('active');
      answers[index].classList.toggle('active');
    };
  });
}

const menubtn = document.querySelector('.menu-btn');

if (menubtn) {
  menubtn.onclick = () => {
    const navMenu = document.querySelector('.nav-menu');
    if (navMenu) navMenu.classList.toggle('active');
  };
}

const ids = document.querySelectorAll('.id');
const admIdMenus = document.querySelectorAll('.adm-id-menu');

if (ids.length > 0 && admIdMenus.length > 0) {
  ids.forEach((id, index) => {
    id.onclick = () => {
      ids[index].classList.toggle('active');
      admIdMenus[index].classList.toggle('active');
    };
  });
}
