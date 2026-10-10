<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import {
  ChevronRight,
  Merge,
  ArrowLeftRight,
  Sparkles,
  NotebookPen,
  Phone,
  CalendarDays,
  AtSign,
} from 'lucide-vue-next';
import Layout from '@/Layouts/Layout.vue';
import Avatar from '@/Shared/Avatar.vue';
import EmptyState from '@/Shared/EmptyState.vue';
import ContactSelector from '@/Shared/Form/ContactSelector.vue';
import { flash } from '@/methods.js';

const props = defineProps({
  layoutData: Object,
  data: Object,
  preselected: Object,
});

const pairs = ref([...props.data.pairs]);
const merging = ref(null);

// manual merge: two contacts picked by hand
const manualA = ref(props.preselected ? [props.preselected] : []);
const manualB = ref([]);

const manualReady = computed(
  () => manualA.value.length === 1 && manualB.value.length === 1 && manualA.value[0].id !== manualB.value[0].id,
);

const merge = (pair, keep, remove) => {
  if (
    !confirm(
      trans(
        'Merge :duplicate into :kept? Everything about :duplicate will move to :kept and :duplicate will be deleted.',
        { duplicate: remove.name, kept: keep.name },
      ),
    )
  ) {
    return;
  }

  merging.value = pair?.id ?? 'manual';

  axios
    .post(props.data.url.merge, { contact_id: keep.id, duplicate_contact_id: remove.id })
    .then((response) => {
      merging.value = null;
      flash(trans(':duplicate has been merged into :kept', { duplicate: remove.name, kept: keep.name }), 'success');

      if (pair) {
        pairs.value = pairs.value.filter(
          (candidate) => candidate.id !== pair.id && !candidate.contacts.some((c) => c.id === remove.id),
        );
      } else {
        router.visit(response.data.data.url);
      }
    })
    .catch((error) => {
      merging.value = null;
      flash(error.response?.data?.message ?? trans('Oops! Something went wrong.'), 'error');
    });
};

const mergeManual = (keepIndex) => {
  const keep = keepIndex === 0 ? manualA.value[0] : manualB.value[0];
  const remove = keepIndex === 0 ? manualB.value[0] : manualA.value[0];
  merge(null, keep, remove);
};
</script>

<template>
  <layout :title="$t('Duplicate contacts')" :layout-data="layoutData" :inside-vault="true">
    <!-- breadcrumb -->
    <nav class="bg-white dark:bg-gray-900 sm:mt-20 sm:border-b sm:border-gray-300 dark:border-gray-700">
      <div class="max-w-8xl mx-auto hidden px-4 py-2 sm:px-6 md:block">
        <div class="flex items-center gap-1 text-sm">
          <div class="text-gray-600 dark:text-gray-400">{{ $t('You are here:') }}</div>
          <Link :href="data.url.contacts" class="text-accent hover:underline">{{ $t('Contacts') }}</Link>
          <ChevronRight class="h-3 w-3" />
          <div>{{ $t('Duplicate contacts') }}</div>
        </div>
      </div>
    </nav>

    <main class="relative sm:mt-8">
      <div class="mx-auto max-w-4xl px-2 py-2 sm:px-6 sm:py-6 lg:px-8">
        <div class="mb-6 flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-soft text-accent">
            <Merge class="h-5 w-5" />
          </span>
          <div>
            <h1 class="text-lg font-semibold text-ink">{{ $t('Duplicate contacts') }}</h1>
            <p class="text-sm text-ink-muted">
              {{
                $t(
                  'Monica looks for people with the same name, email address or phone number. Choose which profile to keep: everything from the other one moves to it.',
                )
              }}
            </p>
          </div>
        </div>

        <!-- detected pairs -->
        <div v-if="pairs.length > 0" class="mb-10 space-y-4">
          <div v-for="pair in pairs" :key="pair.id" class="rounded-lg border border-line bg-surface-card">
            <div class="flex items-center gap-2 border-b border-line px-4 py-2 text-xs font-medium text-ink-muted">
              <Sparkles class="h-3.5 w-3.5" />
              {{ pair.reason }}
            </div>
            <div class="grid grid-cols-1 gap-px bg-line sm:grid-cols-2">
              <div
                v-for="(contact, index) in pair.contacts"
                :key="contact.id"
                class="flex flex-col gap-3 bg-surface-card p-4">
                <div class="flex items-center gap-3">
                  <avatar :data="contact.avatar" :class="'h-12 w-12 rounded-full'" />
                  <div class="min-w-0">
                    <Link :href="contact.url.show" class="block truncate font-medium text-ink hover:underline">{{
                      contact.name
                    }}</Link>
                    <p class="text-xs text-ink-muted">{{ $t('Added on :date', { date: contact.created_at }) }}</p>
                  </div>
                </div>

                <ul class="grid grid-cols-2 gap-1 text-xs text-ink-muted">
                  <li class="flex items-center gap-1 tabular">
                    <NotebookPen class="h-3 w-3" />
                    {{ $tChoice(':count note|:count notes', contact.counts.notes, { count: contact.counts.notes }) }}
                  </li>
                  <li class="flex items-center gap-1 tabular">
                    <Phone class="h-3 w-3" />
                    {{ $tChoice(':count call|:count calls', contact.counts.calls, { count: contact.counts.calls }) }}
                  </li>
                  <li class="flex items-center gap-1 tabular">
                    <CalendarDays class="h-3 w-3" />
                    {{
                      $tChoice(':count date|:count dates', contact.counts.important_dates, {
                        count: contact.counts.important_dates,
                      })
                    }}
                  </li>
                  <li class="flex items-center gap-1 tabular">
                    <AtSign class="h-3 w-3" />
                    {{
                      $tChoice(':count contact detail|:count contact details', contact.counts.contact_information, {
                        count: contact.counts.contact_information,
                      })
                    }}
                  </li>
                </ul>
                <ul v-if="contact.contact_information.length" class="flex flex-wrap gap-1">
                  <li
                    v-for="info in contact.contact_information"
                    :key="info"
                    class="rounded-sm bg-surface-sunken px-1.5 py-0.5 text-[11px] text-ink-muted">
                    {{ info }}
                  </li>
                </ul>

                <button
                  type="button"
                  :disabled="merging === pair.id || !pair.contacts[1 - index].can_be_deleted"
                  class="mt-auto flex items-center justify-center gap-1.5 rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium text-ink transition-[background-color,transform] hover:border-accent hover:bg-accent-soft active:scale-[0.98] disabled:opacity-50"
                  @click="merge(pair, contact, pair.contacts[1 - index])">
                  <Merge class="h-4 w-4" />
                  {{ $t('Keep this one') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <empty-state
          v-else
          :icon="Sparkles"
          :title="$t('No duplicates found')"
          :description="$t('Nobody seems to be in the vault twice. You can still merge two contacts by hand below.')"
          class="mb-10" />

        <!-- manual merge -->
        <div class="rounded-lg border border-line bg-surface-card">
          <div class="flex items-center gap-2 border-b border-line px-4 py-2 text-sm font-medium text-ink">
            <ArrowLeftRight class="h-4 w-4 text-ink-muted" />
            {{ $t('Merge two contacts by hand') }}
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
              <contact-selector
                v-model="manualA"
                :label="$t('First contact')"
                :search-url="data.url.search_contacts_only"
                :most-consulted-contacts-url="data.url.get_most_consulted_contacts"
                :display-most-consulted-contacts="true"
                :add-multiple-contacts="false" />
            </div>
            <div>
              <contact-selector
                v-model="manualB"
                :label="$t('Second contact')"
                :search-url="data.url.search_contacts_only"
                :most-consulted-contacts-url="data.url.get_most_consulted_contacts"
                :display-most-consulted-contacts="true"
                :add-multiple-contacts="false" />
            </div>
          </div>
          <div
            v-if="manualReady"
            class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-surface-raised px-4 py-3 text-sm">
            <span class="me-auto text-ink-muted">{{ $t('Which one do you want to keep?') }}</span>
            <button
              type="button"
              :disabled="merging === 'manual'"
              class="rounded-md border border-line bg-surface px-3 py-1.5 font-medium text-ink hover:border-accent hover:bg-accent-soft disabled:opacity-50"
              @click="mergeManual(0)">
              {{ $t('Keep :name', { name: manualA[0].name }) }}
            </button>
            <button
              type="button"
              :disabled="merging === 'manual'"
              class="rounded-md border border-line bg-surface px-3 py-1.5 font-medium text-ink hover:border-accent hover:bg-accent-soft disabled:opacity-50"
              @click="mergeManual(1)">
              {{ $t('Keep :name', { name: manualB[0].name }) }}
            </button>
          </div>
        </div>
      </div>
    </main>
  </layout>
</template>
