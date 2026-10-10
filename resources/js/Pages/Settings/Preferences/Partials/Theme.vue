<script setup>
import { ref, computed } from 'vue';
import { Sun, Moon, Monitor, Check } from 'lucide-vue-next';
import { trans } from 'laravel-vue-i18n';
import PrettyButton from '@/Shared/Form/PrettyButton.vue';
import PrettyLink from '@/Shared/Form/PrettyLink.vue';
import Errors from '@/Shared/Form/Errors.vue';
import Help from '@/Shared/Help.vue';
import { applyTheme, flash, themeSettings } from '@/methods.js';

const props = defineProps({
  data: Object,
});

const loadingState = ref('');
const editMode = ref(false);
const current = ref({
  theme: props.data.theme,
  theme_i18n: props.data.theme_i18n,
  accent_color: props.data.accent_color,
  oled_dark: props.data.oled_dark,
});
const form = ref({
  theme: props.data.theme,
  accent_color: props.data.accent_color,
  oled_dark: props.data.oled_dark,
  errors: [],
});

const icons = { light: Sun, dark: Moon, system: Monitor };

// Tailwind palettes used for the swatches. Kept in sync with app.css.
const swatches = {
  blue: 'bg-blue-500',
  violet: 'bg-violet-500',
  rose: 'bg-rose-500',
  emerald: 'bg-emerald-600',
  amber: 'bg-amber-500',
  teal: 'bg-teal-600',
};

const currentAccentName = computed(
  () => props.data.accent_colors.find((color) => color.id === current.value.accent_color)?.name ?? '',
);

// A copy of the settings as they were before editing, to restore them on cancel.
let previous = null;

const enableEditMode = () => {
  previous = themeSettings();
  form.value.theme = current.value.theme;
  form.value.accent_color = current.value.accent_color;
  form.value.oled_dark = current.value.oled_dark;
  editMode.value = true;
};

// Live preview while editing: the whole app reflects the choice immediately.
const preview = () => {
  applyTheme({ theme: form.value.theme, accent: form.value.accent_color, oled: form.value.oled_dark });
};

const cancel = () => {
  if (previous) {
    applyTheme(previous);
  }
  editMode.value = false;
};

const submit = () => {
  loadingState.value = 'loading';

  axios
    .post(props.data.url.store, form.value)
    .then((response) => {
      flash(trans('Changes saved'), 'success');
      current.value = response.data.data;
      applyTheme({
        theme: current.value.theme,
        accent: current.value.accent_color,
        oled: current.value.oled_dark,
      });
      editMode.value = false;
      loadingState.value = null;
    })
    .catch((error) => {
      loadingState.value = null;
      form.value.errors = error.response.data;
    });
};
</script>

<template>
  <div class="mb-16">
    <!-- title + cta -->
    <div class="mb-3 mt-8 items-center justify-between sm:mt-0 sm:flex">
      <h3 class="mb-4 flex font-semibold sm:mb-0">
        <span class="me-1"> 🎨 </span>
        <span class="me-2">
          {{ $t('Appearance') }}
        </span>

        <help :url="$page.props.help_links.settings_preferences_help" :top="'5px'" />
      </h3>
      <pretty-button v-if="!editMode" :text="$t('Edit')" @click="enableEditMode" />
    </div>

    <!-- normal mode -->
    <div v-if="!editMode" class="mb-6 rounded-lg border border-line bg-surface dark:bg-gray-900">
      <div class="flex flex-wrap items-center gap-x-8 gap-y-2 px-5 py-3 text-sm">
        <div class="flex items-center gap-2">
          <component :is="icons[current.theme]" class="h-4 w-4 text-ink-muted" />
          <span>{{ current.theme_i18n }}</span>
          <span v-if="current.theme === 'dark' && current.oled_dark" class="text-ink-muted">· OLED</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block h-4 w-4 rounded-full" :class="swatches[current.accent_color]"></span>
          <span>{{ currentAccentName }}</span>
        </div>
      </div>
    </div>

    <!-- edit mode -->
    <form
      v-if="editMode"
      class="mb-6 rounded-lg border border-line bg-surface dark:bg-gray-900"
      @submit.prevent="submit()">
      <div class="border-b border-line px-5 py-4">
        <errors :errors="form.errors" />

        <!-- theme -->
        <p class="mb-2 text-sm font-medium">{{ $t('Theme') }}</p>
        <div class="mb-6 grid grid-cols-1 gap-2 sm:grid-cols-3">
          <label
            v-for="theme in data.themes"
            :key="theme.id"
            :class="
              form.theme === theme.id
                ? 'border-accent bg-accent-soft ring-1 ring-accent'
                : 'border-line hover:border-line-strong'
            "
            class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-[border-color,background-color]">
            <input v-model="form.theme" :value="theme.id" type="radio" name="theme" class="sr-only" @change="preview" />
            <component :is="icons[theme.id]" class="mt-0.5 h-4 w-4 shrink-0 text-ink-muted" />
            <span>
              <span class="block font-medium">{{ theme.name }}</span>
              <span class="block text-sm text-ink-muted">{{ theme.description }}</span>
            </span>
          </label>
        </div>

        <!-- oled -->
        <label class="mb-6 flex cursor-pointer items-center gap-3 text-sm">
          <input
            v-model="form.oled_dark"
            type="checkbox"
            class="h-4 w-4 rounded-xs border-line-strong text-accent focus:ring-accent"
            @change="preview" />
          <span>
            <span class="block">{{ $t('Pure black in dark mode') }}</span>
            <span class="block text-ink-muted">{{ $t('Saves battery on OLED screens and increases contrast.') }}</span>
          </span>
        </label>

        <!-- accent -->
        <p class="mb-2 text-sm font-medium">{{ $t('Accent color') }}</p>
        <div class="flex flex-wrap gap-3">
          <label
            v-for="color in data.accent_colors"
            :key="color.id"
            :title="color.name"
            class="relative flex h-9 w-9 cursor-pointer items-center justify-center rounded-full transition-transform hover:scale-105 active:scale-[0.96]"
            :class="swatches[color.id]">
            <input
              v-model="form.accent_color"
              :value="color.id"
              type="radio"
              name="accent_color"
              class="sr-only"
              :aria-label="color.name"
              @change="preview" />
            <Check v-if="form.accent_color === color.id" class="h-4 w-4 text-white" />
          </label>
        </div>
      </div>

      <!-- actions -->
      <div class="flex justify-between p-5">
        <pretty-link :text="$t('Cancel')" :class="'me-3'" @click="cancel" />
        <pretty-button :text="$t('Save')" :state="loadingState" :icon="'check'" :class="'save'" />
      </div>
    </form>
  </div>
</template>
