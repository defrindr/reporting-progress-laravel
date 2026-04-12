import './bootstrap';

const THEME_KEY = 'internship-theme';

function currentTheme() {
	const active = document.documentElement.getAttribute('data-theme');
	if (active === 'dark' || active === 'light') {
		return active;
	}

	const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	return systemPrefersDark ? 'dark' : 'light';
}

function applyTheme(theme) {
	document.documentElement.setAttribute('data-theme', theme);
	document.documentElement.style.colorScheme = theme;
}

function refreshThemeLabels(theme) {
	const labels = document.querySelectorAll('[data-theme-label]');
	labels.forEach((label) => {
		label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
	});
}

function initThemeToggle() {
	const buttons = document.querySelectorAll('[data-theme-toggle]');
	if (!buttons.length) {
		return;
	}

	let theme = currentTheme();
	applyTheme(theme);
	refreshThemeLabels(theme);

	buttons.forEach((button) => {
		button.addEventListener('click', () => {
			theme = theme === 'dark' ? 'light' : 'dark';
			applyTheme(theme);
			refreshThemeLabels(theme);

			try {
				localStorage.setItem(THEME_KEY, theme);
			} catch (error) {
				// Ignore localStorage failures.
			}
		});
	});
}

document.addEventListener('DOMContentLoaded', initThemeToggle);
