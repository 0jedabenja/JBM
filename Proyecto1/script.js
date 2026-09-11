const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('passwordConfirm');
const togglePasswordBtn = document.getElementById('togglePassword');
const togglePasswordConfirmBtn = document.getElementById('togglePasswordConfirm');
const themeToggleBtn = document.getElementById('themeToggle');

// Actualizamos para que tome ambos candados
const lockIcon1 = document.getElementById('lockIcon1');
const lockIcon2 = document.getElementById('lockIcon2');

const root = document.documentElement;
const openPopupBtn = document.getElementById('openPopup');
const overlay = document.getElementById('overlay-background');
const languageBtn = document.querySelector('.language');

let currentLang = 'es';
let translations = {};

// --- LÓGICA DEL PRIMER OJO ---
togglePasswordBtn.addEventListener('click', () => {
  const isDark = root.getAttribute('data-theme') === 'dark';
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    togglePasswordBtn.src = isDark ? 'icons/dark-eye-off.svg' : 'icons/light-eye-off.svg';
  } else {
    passwordInput.type = 'password';
    togglePasswordBtn.src = isDark ? 'icons/dark-eye.svg' : 'icons/light-eye.svg';
  }
});

// --- LÓGICA DEL SEGUNDO OJO ---
togglePasswordConfirmBtn.addEventListener('click', () => { 
  const isDark = root.getAttribute('data-theme') === 'dark';
  if (passwordConfirmInput.type === 'password') {
    passwordConfirmInput.type = 'text';
    togglePasswordConfirmBtn.src = isDark ? 'icons/dark-eye-off.svg' : 'icons/light-eye-off.svg';
  } else {
    passwordConfirmInput.type = 'password';
    togglePasswordConfirmBtn.src = isDark ? 'icons/dark-eye.svg' : 'icons/light-eye.svg';
  }
});

// --- LÓGICA DEL CAMBIO DE TEMA ---
themeToggleBtn.addEventListener('click', () => {
  const isCurrentlyDark = root.getAttribute('data-theme') === 'dark';
  
  if (isCurrentlyDark) {
    root.removeAttribute('data-theme');
    themeToggleBtn.src = 'icons/sun.svg';
    
    // Candados al tema claro
    if (lockIcon1) lockIcon1.src = 'icons/light-lock.svg';
    if (lockIcon2) lockIcon2.src = 'icons/light-lock.svg';

    // Primer ojo al tema claro
    togglePasswordBtn.src = passwordInput.type === 'text' ? 'icons/light-eye-off.svg' : 'icons/light-eye.svg';
    
    // Segundo ojo al tema claro (ESTO FALTABA)
    togglePasswordConfirmBtn.src = passwordConfirmInput.type === 'text' ? 'icons/light-eye-off.svg' : 'icons/light-eye.svg';
    
  } else {
    root.setAttribute('data-theme', 'dark');
    themeToggleBtn.src = 'icons/moon.svg';
    
    // Candados al tema oscuro
    if (lockIcon1) lockIcon1.src = 'icons/dark-lock.svg';
    if (lockIcon2) lockIcon2.src = 'icons/dark-lock.svg';

    // Primer ojo al tema oscuro
    togglePasswordBtn.src = passwordInput.type === 'text' ? 'icons/dark-eye-off.svg' : 'icons/dark-eye.svg';
    
    // Segundo ojo al tema oscuro (ESTO FALTABA)
    togglePasswordConfirmBtn.src = passwordConfirmInput.type === 'text' ? 'icons/dark-eye-off.svg' : 'icons/dark-eye.svg';
  }
});

// --- LÓGICA DEL POPUP ---
openPopupBtn.addEventListener('click', (e) => {
  e.preventDefault();
  overlay.classList.add('active');
});

overlay.addEventListener('click', (e) => {
  if (e.target === overlay) {
    overlay.classList.remove('active');
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    overlay.classList.remove('active');
  }
});

// --- LÓGICA DE IDIOMAS ---
fetch('lang.json')
  .then(response => response.json())
  .then(data => { translations = data; })
  .catch(error => console.error(error));

function cambiarIdioma(lang) {
  const t = translations[lang];
  if (!t) return;

  document.querySelector('.title').textContent = t['title'];
  document.querySelector('.description').innerHTML = t['description'];
  
  // Eliminadas las llamadas a 'input[type="text"]' y '.help-link' porque no existen aquí y rompían el código
  document.querySelector('#password').placeholder = t['password'];
  document.querySelector('#passwordConfirm').placeholder = t['passwordConfirm'] || 'Confirm Password'; // Ajusta esto según tu JSON
  
  document.querySelector('.btn').textContent = t['button'];
  document.querySelector('.footer-left p').textContent = t['copyright'];
  document.querySelector('.about-us').textContent = t['about-us'];
  document.querySelector('.popup-title').textContent = t['about-us'];
  document.querySelector('.company-description').textContent = t['company'];
  languageBtn.textContent = t['language'];
}

languageBtn.addEventListener('click', () => {
  currentLang = currentLang === 'es' ? 'en' : 'es';
  cambiarIdioma(currentLang);
});