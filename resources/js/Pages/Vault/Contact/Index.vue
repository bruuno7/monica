<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';
import { LayoutList, LayoutGrid, Search, Cake, Users } from 'lucide-vue-next';
import Layout from '@/Layouts/Layout.vue';
import PrettyLink from '@/Shared/Form/PrettyLink.vue';
import Avatar from '@/Shared/Avatar.vue';
import Pagination from '@/Components/Pagination.vue';
import Dropdown from '@/Shared/Form/Dropdown.vue';
import EmptyState from '@/Shared/EmptyState.vue';

const props = defineProps({
  layoutData: Object,
  data: Object,
  paginator: Object,
});

const form = useForm({
  sort_order: props.data.user_contact_sort_order,
});

const filter = ref('');
const view = ref('list');
const activeIndex = ref(-1);
const filterInput = ref(null);

onMounted(() => {
  view.value = localStorage.getItem('monica.contacts.view') ?? 'list';
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
});

watch(view, (value) => localStorage.setItem('monica.contacts.view', value));

const normalise = (text) =>
  (text ?? '')
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

// Instant filter over the contacts of this page: name, job and labels.
const contacts = computed(() => {
  const term = normalise(filter.value.trim());
  if (term === '') {
    return props.data.contacts;
  }

  return props.data.contacts.filter(
    (contact) =>
      normalise(contact.name).includes(term) ||
      normalise(contact.job).includes(term) ||
      contact.labels.some((label) => normalise(label.name).includes(term)),
  );
});

watch(contacts, () => {
  activeIndex.value = -1;
});

const update = () => {
  axios.put(props.data.url.sort.update, form).then((response) => {
    localStorage.success = trans('Changes saved');
    router.visit(response.data.data);
  });
};

const statusDot = {
  fresh: 'bg-emerald-500',
  cooling: 'bg-amber-500',
  cold: 'bg-rose-500',
};

const lastInteraction = (contact) => {
  const days = contact.last_interaction.days_ago;
  if (days === null) return null;
  if (days === 0) return trans('Today');
  if (days === 1) return trans('Yesterday');
  return trans(':count days ago', { count: days });
};

const importantDate = (contact) => {
  const next = contact.next_important_date;
  if (!next) return null;
  if (next.in_days === 0) return trans(':label today!', { label: next.label });
  if (next.in_days === 1) return trans(':label tomorrow', { label: next.label });
  return trans(':label in :count days', { label: next.label, count: next.in_days });
};

const searchEverywhere = () => {
  emitter.emit('command-palette:open', { query: filter.value });
};

const isTyping = (event) => {
  const target = event.target;
  return target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
};

// `/` focuses the filter; arrows move through the list; Enter opens the contact.
const onKeydown = (event) => {
  if (event.key === '/' && !isTyping(event) && !event.metaKey && !event.ctrlKey) {
    event.preventDefault();
    filterInput.value?.focus();
    return;
  }

  const inFilter = event.target === filterInput.value;
  if (isTyping(event) && !inFilter) return;

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    activeIndex.value = Math.min(activeIndex.value + 1, contacts.value.length - 1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    activeIndex.value = Math.max(activeIndex.value - 1, 0);
  } else if (event.key === 'Enter' && activeIndex.value >= 0 && contacts.value[activeIndex.value]) {
    event.preventDefault();
    router.visit(contacts.value[activeIndex.value].url.show);
  } else if (event.key === 'Enter' && inFilter && filter.value.trim().length >= 3 && contacts.value.length === 0) {
    searchEverywhere();
  }
};
</script>

<template>
  <layout :title="$t('Contacts')" :layout-data="layoutData" :inside-vault="true">
    <main class="relative sm:mt-24">
      <div class="mx-auto max-w-6xl px-2 py-2 sm:px-6 sm:py-6 lg:px-8">
        <div class="special-grid grid grid-cols-1 gap-6 sm:grid-cols-3">
          <!-- left -->
          <div>
            <!-- labels -->
            <div class="mb-8">
              <div class="mb-3 border-b border-line pb-1 text-sm font-medium">{{ $t('Labels') }}</div>
              <ul v-if="data.labels.length > 0" class="text-sm">
                <li class="mb-1">
                  <Link v-if="data.current_label" :href="data.url.contact.index" class="text-accent hover:underline">
                    {{ $t('View all') }}
                  </Link>
                  <span v-else class="font-medium text-ink">{{ $t('View all') }}</span>
                </li>
                <li v-for="label in data.labels" :key="label.id" class="mb-1 flex items-center gap-2">
                  <Link
                    v-if="label.id !== data.current_label"
                    :href="label.url.show"
                    class="text-accent hover:underline">
                    {{ label.name }}
                  </Link>
                  <span v-else class="font-medium text-ink">{{ label.name }}</span>
                  <span class="tabular text-xs text-ink-muted">{{ label.count }}</span>
                </li>
              </ul>

              <p v-else class="text-sm text-ink-muted">
                {{ $t('No labels yet.') }}
              </p>
            </div>
          </div>

          <!-- right -->
          <div class="p-3 sm:px-3 sm:py-0">
            <!-- title + cta -->
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
              <h3 class="flex items-baseline gap-2">
                <span class="font-medium">{{ $t('All contacts in the vault') }}</span>
                <span v-if="paginator?.total" class="tabular text-sm text-ink-muted">{{ paginator.total }}</span>
              </h3>

              <div class="flex items-center gap-2">
                <dropdown
                  v-model="form.sort_order"
                  :data="data.contact_sort_orders"
                  :required="false"
                  :dropdown-class="'block w-full'"
                  @change="update()" />

                <!-- view switch -->
                <div class="inline-flex rounded-md border border-line p-0.5" role="radiogroup" :aria-label="$t('View')">
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="view === 'list'"
                    :title="$t('List')"
                    :class="view === 'list' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
                    class="flex h-7 w-7 items-center justify-center rounded-sm transition-colors"
                    @click="view = 'list'">
                    <LayoutList class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="view === 'cards'"
                    :title="$t('Cards')"
                    :class="view === 'cards' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
                    class="flex h-7 w-7 items-center justify-center rounded-sm transition-colors"
                    @click="view = 'cards'">
                    <LayoutGrid class="h-4 w-4" />
                  </button>
                </div>

                <pretty-link
                  v-if="layoutData.vault.permission.at_least_editor"
                  :href="data.url.contact.create"
                  :text="$t('Add a contact')"
                  :icon="'plus'" />
              </div>
            </div>

            <!-- filter -->
            <div v-if="data.contacts.length > 0" class="relative mb-4">
              <Search class="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-ink-subtle" />
              <input
                ref="filterInput"
                v-model="filter"
                type="search"
                class="block w-full rounded-md border-line-strong bg-surface-card py-2 ps-9 pe-10 text-sm placeholder:text-ink-subtle focus:border-accent focus:ring-3 focus:ring-accent-soft dark:bg-slate-900"
                :placeholder="$t('Filter by name, job or label…')"
                autocomplete="off" />
              <kbd
                class="pointer-events-none absolute end-3 top-2.5 hidden rounded-sm border border-line px-1.5 font-sans text-[10px] leading-4 text-ink-subtle sm:inline"
                aria-hidden="true"
                >/</kbd
              >
            </div>

            <!-- list view -->
            <ul
              v-if="view === 'list' && contacts.length > 0"
              class="contact-list mb-6 rounded-lg border border-line bg-surface-card">
              <li v-for="(contact, index) in contacts" :key="contact.id">
                <Link
                  :href="contact.url.show"
                  :class="index === activeIndex ? 'bg-accent-soft' : 'hover:bg-surface-raised'"
                  class="flex items-center gap-3 border-b border-line px-4 py-2.5 transition-colors"
                  @mouseenter="activeIndex = index">
                  <avatar :data="contact.avatar" :class="'h-8 w-8 shrink-0 rounded-full'" />

                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                      <span class="font-medium text-ink">{{ contact.name }}</span>
                      <span
                        v-for="label in contact.labels"
                        :key="label.id"
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium leading-4"
                        :class="label.bg_color + ' ' + label.text_color">
                        {{ label.name }}
                      </span>
                    </div>
                    <p v-if="contact.job" class="truncate text-xs text-ink-muted">{{ contact.job }}</p>
                  </div>

                  <!-- signals -->
                  <div class="hidden shrink-0 items-center gap-3 text-xs text-ink-muted sm:flex">
                    <span
                      v-if="importantDate(contact)"
                      class="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                      <Cake class="h-3 w-3" />
                      {{ importantDate(contact) }}
                    </span>
                    <span
                      v-if="lastInteraction(contact)"
                      class="tabular flex items-center gap-1.5"
                      :title="contact.stay_in_touch.status_label">
                      <span
                        v-if="contact.stay_in_touch.status !== 'off'"
                        class="h-2 w-2 rounded-full"
                        :class="statusDot[contact.stay_in_touch.status]"></span>
                      {{ lastInteraction(contact) }}
                    </span>
                  </div>
                </Link>
              </li>
            </ul>

            <!-- cards view -->
            <ul v-if="view === 'cards' && contacts.length > 0" class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3">
              <li v-for="(contact, index) in contacts" :key="contact.id">
                <Link
                  :href="contact.url.show"
                  :class="
                    index === activeIndex ? 'border-accent ring-1 ring-accent' : 'border-line hover:border-line-strong'
                  "
                  class="flex h-full flex-col items-center gap-2 rounded-lg border bg-surface-card p-4 text-center transition-[border-color,transform] active:scale-[0.98]"
                  @mouseenter="activeIndex = index">
                  <div class="relative">
                    <avatar :data="contact.avatar" :class="'h-16 w-16 rounded-full'" />
                    <span
                      v-if="contact.stay_in_touch.status !== 'off'"
                      class="absolute bottom-0 end-0 h-3.5 w-3.5 rounded-full border-2 border-surface-card"
                      :class="statusDot[contact.stay_in_touch.status]"
                      :title="contact.stay_in_touch.status_label"></span>
                  </div>
                  <span class="font-medium text-ink">{{ contact.name }}</span>
                  <span v-if="contact.job" class="line-clamp-1 text-xs text-ink-muted">{{ contact.job }}</span>
                  <div v-if="contact.labels.length" class="flex flex-wrap justify-center gap-1">
                    <span
                      v-for="label in contact.labels"
                      :key="label.id"
                      class="rounded-full px-2 py-0.5 text-[11px] font-medium leading-4"
                      :class="label.bg_color + ' ' + label.text_color">
                      {{ label.name }}
                    </span>
                  </div>
                  <span
                    v-if="importantDate(contact)"
                    class="mt-auto flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300">
                    <Cake class="h-3 w-3" />
                    {{ importantDate(contact) }}
                  </span>
                  <span v-else-if="lastInteraction(contact)" class="tabular mt-auto text-xs text-ink-muted">
                    {{ lastInteraction(contact) }}
                  </span>
                </Link>
              </li>
            </ul>

            <!-- no match for the filter -->
            <empty-state
              v-if="data.contacts.length > 0 && contacts.length === 0"
              :icon="Search"
              :title="$t('No contact on this page matches “:term”', { term: filter })"
              :description="$t('Search the whole vault to find them.')"
              class="mb-6">
              <button type="button" class="text-sm text-accent hover:underline" @click="searchEverywhere">
                {{ $t('Search everything for “:term”', { term: filter }) }}
              </button>
            </empty-state>

            <!-- no contacts at all -->
            <empty-state
              v-if="data.contacts.length === 0"
              :icon="Users"
              :title="$t('No contacts yet')"
              :description="$t('Add the people you care about and start documenting your life together.')"
              class="mb-6">
              <pretty-link
                v-if="layoutData.vault.permission.at_least_editor"
                :href="data.url.contact.create"
                :text="$t('Add a contact')"
                :icon="'plus'" />
            </empty-state>

            <!-- pagination -->
            <Pagination v-if="filter === ''" :items="paginator" />
          </div>
        </div>
      </div>
    </main>
  </layout>
</template>

<style lang="scss" scoped>
.contact-list {
  li:first-child a {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
  }

  li:last-child a {
    border-bottom: 0;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
  }
}

.special-grid {
  grid-template-columns: 200px 1fr;
}

@media (max-width: 480px) {
  .special-grid {
    grid-template-columns: 1fr;
  }
}
</style>
