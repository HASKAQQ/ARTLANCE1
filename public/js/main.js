const phoneForm = document.getElementById('phoneForm');
const codeInputs = document.querySelectorAll('.code-input');

if (phoneForm) {
  phoneForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const phone = document.getElementById('phoneInput').value;
    const response = await fetch('login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    });
    const data = await response.json();
    if (!data.success) return alert(data.message || 'Ошибка отправки');

    console.log('КОД ПОДТВЕРЖДЕНИЯ ARTLANCE:', data.code);
    alert('Код отправлен. Откройте консоль браузера (F12).');
    document.getElementById('phoneStep').classList.add('d-none');
    document.getElementById('codeStep').classList.remove('d-none');
    if (codeInputs[0]) codeInputs[0].focus();
  });
}

async function verifyCode() {
  const code = [...codeInputs].map(i => i.value).join('');
  if (code.length !== 5) return;

  const response = await fetch('login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=verify_code&code=${encodeURIComponent(code)}`
  });
  const data = await response.json();
  if (!data.success) {
    alert(data.message || 'Ошибка проверки');
    codeInputs.forEach((i) => i.value = '');
    codeInputs[0].focus();
    return;
  }
  window.location.href = data.redirect;
}

codeInputs.forEach((input, idx) => {
  input.addEventListener('input', () => {
    if (input.value.length === 1 && idx < codeInputs.length - 1) codeInputs[idx + 1].focus();
    if (idx === codeInputs.length - 1 && input.value) verifyCode();
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !input.value && idx > 0) codeInputs[idx - 1].focus();
  });
});

const resendBtn = document.getElementById('resendBtn');
if (resendBtn) {
  resendBtn.addEventListener('click', () => {
    document.getElementById('phoneStep').classList.remove('d-none');
    document.getElementById('codeStep').classList.add('d-none');
  });
}
