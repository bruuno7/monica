<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Sun, Moon, Monitor } from 'lucide-vue-next';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';
import { applyTheme, currentTheme, themeSettings } from '@/methods.js';

defineProps({
  // Compact mode shows a single button that cycles through the themes (used on mobile).
  compact: { type: Boolean, default: false },
});

const page = usePage();
const theme = ref('system');

const options = computed(() => [
  { id: 'light', label: trans('Light'), icon: Sun },
  { id: 'dark', label: trans('Dark'), icon: Moon },
  { id: 'system', label: trans('System'), icon: Monitor },
]);

const current = computed(() => options.value.find((option) => option.id === theme.value) ?? options.value[2]);

const onThemeChanged = (settings) => {
  theme.value = settings.theme;
};

onMounted(() => {
  theme.value = currentTheme();
  emitter.on('theme-changed', onThemeChanged);
});

onBeforeUnmount(() => {
  emitter.off('theme-changed', onThemeChanged);
});

const select = (id) => {
  applyTheme({ theme: id });

  // Persist the choice on the account so it follows the user across devices.
  if (page.props.auth?.user) {
    const settings = themeSettings();

    axios
      .post(route('settings.preferences.theme.store'), {
        theme: id,
        accent_color: settings.accent,
        oled_dark: settings.oled,
      })
      .catch(() => {});
  }
};

const cycle = () => {
  const index = options.value.findIndex((option) => option.id === theme.value);
  select(options.value[(index + 1) % options.value.length].id);
};
</script>

<template>
  <!-- compact: one button cycling light → dark → system -->
  <button
    v-if="compact"
    type="button"
    class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-surface text-ink transition-transform active:scale-[0.96] dark:bg-gray-900"
    :title="$t('Appearance: :theme', { theme: current.label })"
    :aria-label="$t('Appearance: :theme', { theme: current.label })"
    @click="cycle">
    <component :is="current.icon" class="h-4 w-4" />
  </button>

  <!-- full: segmented control -->
  <div
    v-else
    role="radiogroup"
    :aria-label="$t('Appearance')"
    class="inline-flex items-center rounded-full border border-line bg-surface p-0.5 dark:bg-gray-900">
    <button
      v-for="option in options"
      :key="option.id"
      type="button"
      role="radio"
      :aria-checked="theme === option.id"
      :title="option.label"
      :aria-label="option.label"
      :class="
        theme === option.id
          ? 'bg-accent text-accent-contrast shadow-xs'
          : 'text-ink-muted hover:bg-surface-sunken hover:text-ink'
      "
      class="flex h-6 w-6 items-center justify-center rounded-full transition-[background-color,color,transform] duration-150 active:scale-[0.96]"
      @click="select(option.id)">
      <component :is="option.icon" class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
