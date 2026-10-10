<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { debounce } from 'lodash';
import emitter from 'tiny-emitter/instance';
import {
  Search,
  LayoutDashboard,
  Users,
  User,
  CalendarDays,
  BookOpen,
  UsersRound,
  Building2,
  SquareCheckBig,
  ChartColumn,
  Folder,
  Settings,
  Vault,
  NotebookPen,
  Phone,
  BellRing,
  Sun,
  Moon,
  Monitor,
  CornerDownLeft,
  Keyboard,
} from 'lucide-vue-next';
import Avatar from '@/Shared/Avatar.vue';
import { applyTheme, themeSettings } from '@/methods.js';

/**
 * Command palette (⌘K / Ctrl+K): jump to a contact or a page, start a quick capture,
 * switch the theme. Also owns the `g` + key navigation shortcuts and the `?` help.
 */
const props = defineProps({
  layoutData: Object,
});

const open = ref(false);
const view = ref('palette'); // palette | help
const query = ref('');
const index = ref(0);
const contacts = ref([]);
const mostConsulted = ref([]);
const searching = ref(false);
const input = useTemplateRef('input');

const vault = computed(() => props.layoutData?.vault ?? null);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const modKey = isMac ? '⌘' : 'Ctrl';

/* ------------------------------------------------------------------ */
/* actions                                                             */
/* ------------------------------------------------------------------ */

const go = (url) => () => router.visit(url);

const navigation = computed(() => {
  const items = [];
  if (vault.value) {
    const url = vault.value.url;
    const visibility = vault.value.visibility ?? {};
    items.push({
      id: 'dashboard',
      label: trans('Dashboard'),
      icon: LayoutDashboard,
      keys: 'g d',
      run: go(url.dashboard),
    });
    items.push({ id: 'contacts', label: trans('Contacts'), icon: Users, keys: 'g c', run: go(url.contacts) });
    if (visibility.show_calendar_tab)
      items.push({ id: 'calendar', label: trans('Calendar'), icon: CalendarDays, keys: 'g k', run: go(url.calendar) });
    if (visibility.show_journal_tab)
      items.push({ id: 'journals', label: trans('Journals'), icon: BookOpen, keys: 'g j', run: go(url.journals) });
    if (visibility.show_group_tab)
      items.push({ id: 'groups', label: trans('Groups'), icon: UsersRound, run: go(url.groups) });
    if (visibility.show_companies_tab)
      items.push({ id: 'companies', label: trans('Companies'), icon: Building2, run: go(url.companies) });
    if (visibility.show_tasks_tab)
      items.push({ id: 'tasks', label: trans('Tasks'), icon: SquareCheckBig, keys: 'g t', run: go(url.tasks) });
    if (visibility.show_reports_tab)
      items.push({ id: 'reports', label: trans('Reports'), icon: ChartColumn, run: go(url.reports) });
    if (visibility.show_files_tab) items.push({ id: 'files', label: trans('Files'), icon: Folder, run: go(url.files) });
  }
  items.push({
    id: 'vaults',
    label: trans('All vaults'),
    icon: Vault,
    keys: 'g v',
    run: go(props.layoutData.url.vaults),
  });
  items.push({
    id: 'settings',
    label: trans('Settings'),
    icon: Settings,
    keys: 'g s',
    run: go(props.layoutData.url.settings),
  });
  return items;
});

const capture = (type) => () => emitter.emit('quick-capture:open', { type });

const creation = computed(() =>
  vault.value?.permission?.at_least_editor
    ? [
        { id: 'new-note', label: trans('New note'), icon: NotebookPen, keys: 'n', run: capture('note') },
        { id: 'new-call', label: trans('Log a call'), icon: Phone, run: capture('call') },
        { id: 'new-task', label: trans('New task'), icon: SquareCheckBig, run: capture('task') },
        { id: 'new-reminder', label: trans('New reminder'), icon: BellRing, run: capture('reminder') },
      ]
    : [],
);

const persistTheme = (theme) => {
  const settings = themeSettings();
  axios
    .post(route('settings.preferences.theme.store'), { theme, accent_color: settings.accent, oled_dark: settings.oled })
    .catch(() => {});
};

const themes = computed(() => [
  {
    id: 'theme-light',
    label: trans('Theme: light'),
    icon: Sun,
    run: () => (applyTheme({ theme: 'light' }), persistTheme('light')),
  },
  {
    id: 'theme-dark',
    label: trans('Theme: dark'),
    icon: Moon,
    run: () => (applyTheme({ theme: 'dark' }), persistTheme('dark')),
  },
  {
    id: 'theme-system',
    label: trans('Theme: system'),
    icon: Monitor,
    run: () => (applyTheme({ theme: 'system' }), persistTheme('system')),
  },
]);

const matches = (label) => label.toLowerCase().includes(query.value.trim().toLowerCase());

const groups = computed(() => {
  const term = query.value.trim();
  const result = [];

  if (vault.value) {
    const list = term.length >= 3 ? contacts.value : mostConsulted.value.filter((c) => matches(c.name));
    result.push({
      id: 'contacts',
      title: term.length >= 3 ? trans('Contacts') : trans('Recent contacts'),
      items: list.slice(0, 6).map((contact) => ({
        id: `contact-${contact.id}`,
        label: contact.name,
        avatar: contact.avatar,
        icon: User,
        run: go(contact.url),
      })),
    });

    if (term.length >= 3) {
      result.push({
        id: 'search',
        title: trans('Search'),
        items: [
          {
            id: 'search-all',
            label: trans('Search everything for “:term”', { term }),
            icon: Search,
            run: () => router.visit(vault.value.url.search, { data: { searchTerm: term } }),
          },
        ],
      });
    }
  }

  const actions = [...creation.value, ...navigation.value, ...themes.value].filter(
    (item) => term === '' || matches(item.label),
  );
  if (actions.length) {
    result.push({ id: 'actions', title: trans('Actions'), items: actions });
  }

  return result.filter((group) => group.items.length > 0);
});

const flat = computed(() => groups.value.flatMap((group) => group.items));

watch(flat, () => {
  index.value = 0;
});

/* ------------------------------------------------------------------ */
/* search                                                              */
/* ------------------------------------------------------------------ */

const normalise = (contact) => ({
  id: contact.id,
  name: (contact.name ?? '').replace(/\s+/g, ' ').trim(),
  url: contact.url?.show ?? contact.url,
  avatar: contact.avatar ?? null,
});

const loadMostConsulted = () => {
  if (!vault.value || mostConsulted.value.length > 0) return;
  axios
    .get(vault.value.url.get_most_consulted_contacts)
    .then((response) => {
      mostConsulted.value = response.data.data.map(normalise);
    })
    .catch(() => {});
};

const search = debounce((term) => {
  if (!vault.value || term.length < 3) {
    contacts.value = [];
    searching.value = false;
    return;
  }

  searching.value = true;
  axios
    .post(vault.value.url.search_contacts_only, { searchTerm: term })
    .then((response) => {
      contacts.value = response.data.data.map(normalise);
      searching.value = false;
    })
    .catch(() => {
      searching.value = false;
    });
}, 200);

watch(query, (term) => {
  if (term.trim().length >= 3) searching.value = true;
  search(term.trim());
});

/* ------------------------------------------------------------------ */
/* open / close / keyboard                                             */
/* ------------------------------------------------------------------ */

const show = (initial = '') => {
  query.value = initial;
  view.value = 'palette';
  index.value = 0;
  open.value = true;
  loadMostConsulted();
  nextTick(() => input.value?.focus());
};

const showHelp = () => {
  view.value = 'help';
  open.value = true;
};

const hide = () => {
  open.value = false;
};

const run = (item) => {
  hide();
  item.run();
};

const onInputKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    index.value = (index.value + 1) % Math.max(flat.value.length, 1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    index.value = (index.value - 1 + flat.value.length) % Math.max(flat.value.length, 1);
  } else if (event.key === 'Enter' && flat.value[index.value]) {
    event.preventDefault();
    run(flat.value[index.value]);
  }
};

const isTyping = (event) => {
  const target = event.target;
  return target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
};

let pendingG = 0;

const onKeydown = (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    open.value && view.value === 'palette' ? hide() : show();
    return;
  }

  if (event.key === 'Escape' && open.value) {
    hide();
    return;
  }

  if (open.value || isTyping(event) || event.metaKey || event.ctrlKey || event.altKey) return;

  if (event.key === '?') {
    event.preventDefault();
    showHelp();
    return;
  }

  if (event.key === 'g') {
    pendingG = Date.now();
    return;
  }

  if (pendingG && Date.now() - pendingG < 1200) {
    const target = [...navigation.value].find((item) => item.keys === `g ${event.key}`);
    pendingG = 0;
    if (target) {
      event.preventDefault();
      target.run();
    }
  }
};

const onOpenRequest = (payload) => show(payload?.query ?? '');

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
  emitter.on('command-palette:open', onOpenRequest);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  emitter.off('command-palette:open', onOpenRequest);
});

const shortcuts = computed(() => [
  { keys: [`${modKey} K`], label: trans('Open the command palette') },
  { keys: ['N'], label: trans('Quick capture') },
  { keys: ['G', 'D'], label: trans('Go to the dashboard') },
  { keys: ['G', 'C'], label: trans('Go to the contacts') },
  { keys: ['G', 'K'], label: trans('Go to the calendar') },
  { keys: ['G', 'J'], label: trans('Go to the journals') },
  { keys: ['G', 'T'], label: trans('Go to the tasks') },
  { keys: ['G', 'V'], label: trans('Go to the vaults') },
  { keys: ['G', 'S'], label: trans('Go to the settings') },
  { keys: ['?'], label: trans('Show this help') },
]);

defineExpose({ show });
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-start justify-center px-3 pt-[12vh]"
      role="dialog"
      aria-modal="true">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="hide"></div>

      <!-- palette -->
      <div
        v-if="view === 'palette'"
        class="relative w-full max-w-xl overflow-hidden rounded-xl border border-line bg-surface-card shadow-2xl">
        <div class="flex items-center gap-3 border-b border-line px-4">
          <Search class="h-4 w-4 shrink-0 text-ink-subtle" />
          <input
            ref="input"
            v-model="query"
            type="text"
            class="h-12 w-full border-0 bg-transparent p-0 text-base text-ink placeholder:text-ink-subtle focus:ring-0"
            :placeholder="vault ? $t('Find a contact, or type a command…') : $t('Type a command…')"
            autocomplete="off"
            spellcheck="false"
            @keydown="onInputKeydown" />
          <kbd
            class="hidden rounded-sm border border-line px-1.5 font-sans text-[10px] leading-5 text-ink-muted sm:inline"
            >Esc</kbd
          >
        </div>

        <div class="max-h-[60vh] overflow-y-auto py-2">
          <template v-for="group in groups" :key="group.id">
            <p class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-ink-subtle">
              {{ group.title }}
            </p>
            <button
              v-for="item in group.items"
              :key="item.id"
              type="button"
              :class="flat.indexOf(item) === index ? 'bg-accent-soft text-ink' : 'text-ink'"
              class="flex w-full items-center gap-3 px-4 py-2 text-start text-sm hover:bg-accent-soft"
              @mouseenter="index = flat.indexOf(item)"
              @click="run(item)">
              <avatar v-if="item.avatar" :data="item.avatar" :class="'h-5 w-5 rounded-full'" />
              <component :is="item.icon" v-else-if="item.icon" class="h-4 w-4 shrink-0 text-ink-muted" />
              <span class="flex-1 truncate">{{ item.label }}</span>
              <span v-if="item.keys" class="flex gap-1">
                <kbd
                  v-for="key in item.keys.split(' ')"
                  :key="key"
                  class="rounded-sm border border-line px-1.5 font-sans text-[10px] uppercase leading-5 text-ink-muted"
                  >{{ key }}</kbd
                >
              </span>
              <CornerDownLeft v-if="flat.indexOf(item) === index" class="h-3.5 w-3.5 text-ink-subtle" />
            </button>
          </template>

          <p v-if="flat.length === 0 && !searching" class="px-4 py-6 text-center text-sm text-ink-muted">
            {{ $t('No results found') }}
          </p>
          <p v-if="flat.length === 0 && searching" class="px-4 py-6 text-center text-sm text-ink-muted">
            {{ $t('Searching…') }}
          </p>
        </div>

        <div
          class="flex items-center justify-between border-t border-line bg-surface-raised px-4 py-2 text-xs text-ink-muted">
          <span>{{ $t('↑↓ to navigate · ↵ to open') }}</span>
          <button type="button" class="flex items-center gap-1 hover:text-ink" @click="view = 'help'">
            <Keyboard class="h-3.5 w-3.5" />
            {{ $t('Shortcuts') }}
          </button>
        </div>
      </div>

      <!-- help -->
      <div
        v-else
        class="relative w-full max-w-md overflow-hidden rounded-xl border border-line bg-surface-card shadow-2xl">
        <div class="flex items-center gap-2 border-b border-line px-5 py-3">
          <Keyboard class="h-4 w-4 text-ink-muted" />
          <h2 class="font-semibold text-ink">{{ $t('Keyboard shortcuts') }}</h2>
        </div>
        <ul class="divide-y divide-line px-5">
          <li
            v-for="shortcut in shortcuts"
            :key="shortcut.label"
            class="flex items-center justify-between py-2 text-sm">
            <span class="text-ink">{{ shortcut.label }}</span>
            <span class="flex gap-1">
              <kbd
                v-for="key in shortcut.keys"
                :key="key"
                class="rounded-sm border border-line bg-surface-raised px-1.5 font-sans text-[11px] leading-5 text-ink-muted"
                >{{ key }}</kbd
              >
            </span>
          </li>
        </ul>
        <div class="border-t border-line bg-surface-raised px-5 py-2 text-end text-xs text-ink-muted">
          <button type="button" class="hover:text-ink" @click="hide">{{ $t('Close') }} · Esc</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
