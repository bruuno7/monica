import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';

/**
 * Flash a message.
 *
 * @param {string} message
 * @param {string} level
 */
export const flash = (message, level = 'success') => {
  emitter.emit('flash', { message, level });
};

const hasWindow = () => typeof window !== 'undefined';

const prefersDark = () => hasWindow() && window.matchMedia('(prefers-color-scheme: dark)').matches;

/**
 * The theme currently applied: light, dark or system.
 *
 * @return {string}
 */
export const currentTheme = () => (hasWindow() && localStorage.theme) || 'system';

/**
 * The theme settings currently applied in the browser.
 *
 * @return {{theme: string, oled: boolean, accent: string}}
 */
export const themeSettings = () => ({
  theme: currentTheme(),
  oled: hasWindow() && localStorage.oled === 'true',
  accent: (hasWindow() && localStorage.accent) || 'blue',
});

export const isDark = () => {
  const theme = currentTheme();

  return theme === 'dark' || (theme === 'system' && prefersDark());
};

/**
 * Apply a theme to the document and remember it in the browser.
 * The server-side preference is saved separately, by the component that calls this.
 *
 * @param {{theme?: string, oled?: boolean, accent?: string}} settings
 * @param {{animate?: boolean}} options
 */
export const applyTheme = (settings = {}, { animate = true } = {}) => {
  if (!hasWindow()) {
    return;
  }

  const { theme, oled, accent } = { ...themeSettings(), ...settings };
  const dark = theme === 'dark' || (theme === 'system' && prefersDark());
  const root = document.documentElement;

  if (animate) {
    root.classList.add('theme-transition');
    window.setTimeout(() => root.classList.remove('theme-transition'), 300);
  }

  root.classList.toggle('dark', dark);
  root.classList.toggle('oled', dark && oled);
  root.dataset.accent = accent;

  localStorage.theme = theme;
  localStorage.oled = oled;
  localStorage.accent = accent;

  emitter.emit('theme-changed', { theme, dark, oled, accent });
};

let systemThemeWatched = false;

/**
 * Follow the operating system appearance while the theme is set to "system".
 */
export const watchSystemTheme = () => {
  if (systemThemeWatched || !hasWindow()) {
    return;
  }

  systemThemeWatched = true;
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (currentTheme() === 'system') {
      applyTheme();
    }
  });
};

/**
 * Get the message in case WebAuthn is not supported.
 *
 * @return {string}
 */
export const webAuthnNotSupportedMessage = computed(() =>
  !window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
    ? trans('WebAuthn only supports secure connections. Please load this page with https scheme.')
    : trans('Your browser doesn’t currently support WebAuthn.'),
);

export default {
  flash,
  isDark,
  currentTheme,
  themeSettings,
  applyTheme,
  watchSystemTheme,
  $on: (...args) => emitter.on(...args),
  $once: (...args) => emitter.once(...args),
  $off: (...args) => emitter.off(...args),
};
