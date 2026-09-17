const root = document.documentElement;
const passwordInput = document.querySelector('input[name="password"]');
const userIcon = document.querySelector('.icon-user');
const lockIcon = document.querySelector('.icon-lock');
const passwordToggle = document.querySelector('.icon-password');
const themeToggle = document.querySelector('.icon-theme');
const openPopupButton = document.querySelector('#openPopup');
const overlay = document.querySelector('.overlay-background');
const languageButton = document.querySelector('.language');

let currentLanguage = 'es';
let translations = {};

function updateLoginIcons() {
	const isDark = root.dataset.theme === 'dark';
	const iconPrefix = isDark ? 'dark' : 'light';

	if (themeToggle) {
		themeToggle.src = `assets/img/login/${isDark ? 'dark-moon' : 'light-sun'}.svg`;
	}

	if (userIcon) {
		userIcon.src = `assets/img/login/${iconPrefix}-user.svg`;
	}

	if (lockIcon) {
		lockIcon.src = `assets/img/login/${iconPrefix}-lock.svg`;
	}

	if (passwordToggle && passwordInput) {
		const iconName = passwordInput.type === 'text' ? 'eye-off' : 'eye';
		passwordToggle.src = `assets/img/login/${iconPrefix}-${iconName}.svg`;
	}
}

if (passwordToggle && passwordInput) {
	passwordToggle.addEventListener('click', () => {
		passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
		updateLoginIcons();
	});
}

if (themeToggle) {
	themeToggle.addEventListener('click', () => {
		root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
		updateLoginIcons();
	});
}

if (openPopupButton && overlay) {
	openPopupButton.addEventListener('click', () => {
		overlay.classList.add('active');
	});

	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) {
			overlay.classList.remove('active');
		}
	});
}

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && overlay) {
		overlay.classList.remove('active');
	}
});

function applyLanguage(language) {
	const translation = translations[language];
	if (!translation) return;

	document.querySelector('.form-title').textContent = translation.title;
	document.querySelector('.form-description').innerHTML = translation.description;
	document.querySelector('input[name="username"]').placeholder = translation.username;
	document.querySelector('input[name="password"]').placeholder = translation.password;
	document.querySelector('.forgot-password').textContent = translation.help;
	document.querySelector('.btn-login').textContent = translation.button;
	document.querySelector('.about-us').textContent = translation['about-us'];
	document.querySelector('.popup-title').textContent = translation['about-us'];
	document.querySelector('.company-description').textContent = translation.company;
	document.querySelector('.footer-left p').innerHTML = translation.copyright;
	languageButton.textContent = translation.language;
}

if (languageButton) {
	languageButton.addEventListener('click', () => {
		currentLanguage = currentLanguage === 'es' ? 'en' : 'es';
		applyLanguage(currentLanguage);
	});
}

updateLoginIcons();

fetch('assets/lang/lang.json')
	.then((response) => response.json())
	.then((data) => {
		translations = data;
	})
	.catch((error) => console.error('No se pudieron cargar las traducciones:', error));
