<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';
import {
  Sparkles,
  PenLine,
  Cake,
  BellRing,
  SquareCheckBig,
  HeartHandshake,
  Check,
  History,
  BookOpen,
  Flame,
  NotebookPen,
  Star,
  ChevronRight,
} from 'lucide-vue-next';
import Layout from '@/Layouts/Layout.vue';
import Avatar from '@/Shared/Avatar.vue';
import MoodTrackingEvents from '@/Pages/Vault/Dashboard/Partials/MoodTrackingEvents.vue';
import ActivityHeatmap from '@/Pages/Vault/Today/Partials/ActivityHeatmap.vue';
import { flash } from '@/methods.js';

const props = defineProps({
  layoutData: Object,
  data: Object,
});

const reachOut = ref([...props.data.reach_out]);
// collections filtered server-side may serialise as objects: normalise to arrays
const reminders = computed(() => Object.values(props.data.reminders?.reminders ?? {}).slice(0, 5));
const tasks = computed(() => Object.values(props.data.due_tasks?.tasks ?? {}).slice(0, 5));

const writeAbout = (contact, prefix = '') => {
  emitter.emit('quick-capture:open', {
    type: 'note',
    contacts: contact ? [{ id: contact.id, name: contact.name, url: contact.url }] : [],
    body: prefix,
  });
};

const writePrompt = () => {
  const prompt = props.data.prompt;
  const contact = prompt.contact ? { id: prompt.contact.id, name: prompt.contact.name, url: prompt.contact.url } : null;
  emitter.emit('quick-capture:open', {
    type: 'note',
    contacts: contact ? [contact] : [],
    body: `> ${prompt.text}\n\n`,
  });
};

const talked = (contact) => {
  axios.post(contact.url.touch).then(() => {
    reachOut.value = reachOut.value.filter((c) => c.id !== contact.id);
    flash(trans('Great! Interaction recorded for today.'), 'success');
  });
};

const dateLabel = (date) => {
  if (date.in_days === 0) return trans('Today');
  if (date.in_days === 1) return trans('Tomorrow');
  return trans('In :count days', { count: date.in_days });
};

const memoryIcon = { post: BookOpen, life_event: Flame, note: NotebookPen };
</script>

<template>
  <layout :title="$t('Today')" :layout-data="layoutData" :inside-vault="true">
    <main class="relative sm:mt-24">
      <div class="max-w-8xl mx-auto px-3 py-4 sm:px-6 lg:px-8">
        <!-- greeting -->
        <header class="mb-6">
          <p class="text-sm capitalize text-ink-muted">{{ data.date }}</p>
          <h1 class="text-2xl font-semibold text-ink">{{ data.greeting }}</h1>
        </header>

        <div class="today-grid grid grid-cols-1 gap-6">
          <!-- main column -->
          <div class="space-y-6">
            <!-- prompt -->
            <section class="relative overflow-hidden rounded-lg border border-line bg-surface-card p-5">
              <div class="pointer-events-none absolute -end-6 -top-6 h-28 w-28 rounded-full bg-accent-soft"></div>
              <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center">
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent text-accent-contrast">
                  <Sparkles class="h-5 w-5" />
                </span>
                <div class="flex-1">
                  <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">
                    {{ data.prompt.type === 'contact' ? $t('Someone to write about') : $t('Today’s prompt') }}
                  </p>
                  <p class="text-base text-ink text-pretty">{{ data.prompt.text }}</p>
                </div>
                <button
                  type="button"
                  class="flex shrink-0 items-center justify-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast transition-[transform,filter] hover:brightness-110 active:scale-[0.96]"
                  @click="writePrompt">
                  <PenLine class="h-4 w-4" />
                  {{ $t('Write about it') }}
                </button>
              </div>
            </section>

            <!-- agenda -->
            <section class="rounded-lg border border-line bg-surface-card">
              <h2 class="border-b border-line px-5 py-3 font-semibold text-ink">{{ $t('Coming up') }}</h2>
              <div class="grid grid-cols-1 divide-y divide-line md:grid-cols-3 md:divide-x md:divide-y-0">
                <!-- important dates -->
                <div class="p-4">
                  <h3 class="mb-2 flex items-center gap-2 text-sm font-medium text-ink">
                    <Cake class="h-4 w-4 text-ink-muted" /> {{ $t('Important dates') }}
                  </h3>
                  <ul v-if="data.important_dates.length" class="space-y-2">
                    <li v-for="date in data.important_dates" :key="date.id" class="flex items-center gap-2 text-sm">
                      <avatar :data="date.contact.avatar" :class="'h-6 w-6 shrink-0 rounded-full'" />
                      <div class="min-w-0 flex-1">
                        <Link :href="date.contact.url" class="block truncate text-ink hover:underline">{{
                          date.contact.name
                        }}</Link>
                        <p class="truncate text-xs text-ink-muted">
                          {{ date.label }}<span v-if="date.age"> · {{ $t(':count years', { count: date.age }) }}</span>
                        </p>
                      </div>
                      <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="
                          date.in_days === 0
                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'
                            : 'bg-surface-sunken text-ink-muted'
                        ">
                        {{ dateLabel(date) }}
                      </span>
                    </li>
                  </ul>
                  <p v-else class="text-sm text-ink-muted">{{ $t('Nothing in the next 7 days.') }}</p>
                </div>

                <!-- reminders -->
                <div class="p-4">
                  <h3 class="mb-2 flex items-center gap-2 text-sm font-medium text-ink">
                    <BellRing class="h-4 w-4 text-ink-muted" /> {{ $t('Reminders') }}
                  </h3>
                  <ul v-if="reminders.length" class="space-y-2">
                    <li v-for="reminder in reminders" :key="reminder.id" class="flex items-center gap-2 text-sm">
                      <avatar :data="reminder.contact.avatar" :class="'h-6 w-6 shrink-0 rounded-full'" />
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-ink">{{ reminder.label }}</p>
                        <p class="truncate text-xs text-ink-muted">
                          {{ reminder.contact.name }} · {{ reminder.scheduled_at }}
                        </p>
                      </div>
                    </li>
                  </ul>
                  <p v-else class="text-sm text-ink-muted">{{ $t('No upcoming reminders.') }}</p>
                </div>

                <!-- tasks -->
                <div class="p-4">
                  <h3 class="mb-2 flex items-center gap-2 text-sm font-medium text-ink">
                    <SquareCheckBig class="h-4 w-4 text-ink-muted" /> {{ $t('Tasks') }}
                  </h3>
                  <ul v-if="tasks.length" class="space-y-2">
                    <li v-for="task in tasks" :key="task.id" class="flex items-center gap-2 text-sm">
                      <avatar :data="task.contact.avatar" :class="'h-6 w-6 shrink-0 rounded-full'" />
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-ink">{{ task.label }}</p>
                        <p class="truncate text-xs text-ink-muted">
                          {{ task.contact.name }}<span v-if="task.due_at"> · {{ task.due_at.formatted }}</span>
                        </p>
                      </div>
                    </li>
                  </ul>
                  <p v-else class="text-sm text-ink-muted">{{ $t('No tasks.') }}</p>
                  <Link
                    v-if="data.due_tasks.url?.index"
                    :href="data.due_tasks.url.index"
                    class="mt-2 inline-flex items-center gap-1 text-xs text-accent hover:underline">
                    {{ $t('View all') }} <ChevronRight class="h-3 w-3" />
                  </Link>
                </div>
              </div>
            </section>

            <!-- reach out -->
            <section class="rounded-lg border border-line bg-surface-card">
              <h2 class="flex items-center gap-2 border-b border-line px-5 py-3 font-semibold text-ink">
                <HeartHandshake class="h-4 w-4 text-ink-muted" />
                {{ $t('People to reach out to') }}
              </h2>
              <ul v-if="reachOut.length" class="divide-y divide-line">
                <li v-for="contact in reachOut" :key="contact.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                  <avatar :data="contact.avatar" :class="'h-9 w-9 shrink-0 rounded-full'" />
                  <div class="min-w-0 flex-1">
                    <Link :href="contact.url.show" class="block truncate font-medium text-ink hover:underline">{{
                      contact.name
                    }}</Link>
                    <p class="text-xs text-ink-muted">
                      <span
                        class="me-1 inline-block h-2 w-2 rounded-full"
                        :class="contact.status === 'cold' ? 'bg-rose-500' : 'bg-amber-500'"></span>
                      {{ $t('Last interaction: :count days ago', { count: contact.days_since_last_interaction }) }}
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="flex items-center gap-1.5 rounded-md border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:border-accent hover:bg-accent-soft active:scale-[0.96]"
                      @click="writeAbout(contact)">
                      <PenLine class="h-3.5 w-3.5" /> {{ $t('Write') }}
                    </button>
                    <button
                      type="button"
                      class="flex items-center gap-1.5 rounded-md border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:border-accent hover:bg-accent-soft active:scale-[0.96]"
                      @click="talked(contact)">
                      <Check class="h-3.5 w-3.5" /> {{ $t('We talked') }}
                    </button>
                  </div>
                </li>
              </ul>
              <p v-else class="px-5 py-6 text-center text-sm text-ink-muted">
                {{ $t('Everyone you wanted to stay in touch with has heard from you recently. Nice.') }}
              </p>
            </section>

            <!-- memories -->
            <section class="rounded-lg border border-line bg-surface-card">
              <h2 class="flex items-center gap-2 border-b border-line px-5 py-3 font-semibold text-ink">
                <History class="h-4 w-4 text-ink-muted" />
                {{ $t('On this day') }}
              </h2>
              <ul v-if="data.memories.length" class="divide-y divide-line">
                <li v-for="(memory, index) in data.memories" :key="index" class="flex items-start gap-3 px-5 py-3">
                  <span
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent">
                    <component :is="memoryIcon[memory.type]" class="h-4 w-4" />
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">
                      {{
                        $tChoice(':count year ago|:count years ago', memory.years_ago, { count: memory.years_ago })
                      }}
                      · {{ memory.year }}
                    </p>
                    <component
                      :is="memory.url ? Link : 'p'"
                      :href="memory.url"
                      class="block font-medium text-ink"
                      :class="memory.url ? 'hover:underline' : ''">
                      {{ memory.title }}
                    </component>
                    <p v-if="memory.excerpt" class="line-clamp-2 text-sm text-ink-muted">{{ memory.excerpt }}</p>
                  </div>
                </li>
              </ul>
              <p v-else class="px-5 py-6 text-center text-sm text-ink-muted">
                {{ $t('No memories for this date yet. Everything you write today will show up here next year.') }}
              </p>
            </section>

            <!-- activity -->
            <activity-heatmap :data="data.activity" />
          </div>

          <!-- aside -->
          <aside class="space-y-6">
            <mood-tracking-events :data="data.mood" />

            <div v-if="data.favorites.length">
              <h3 class="mb-3 flex items-center gap-2 border-b border-line pb-1 font-medium">
                <Star class="h-4 w-4 text-ink-muted" />
                {{ $t('Favorites') }}
              </h3>
              <ul class="space-y-2">
                <li v-for="contact in data.favorites" :key="contact.id" class="flex items-center gap-2 text-sm">
                  <avatar :data="contact.avatar" :class="'h-6 w-6 rounded-full'" />
                  <Link :href="contact.url.show" class="text-accent hover:underline">{{ contact.name }}</Link>
                </li>
              </ul>
            </div>

            <p class="text-xs text-ink-muted">
              <Link :href="data.url.dashboard" class="text-accent hover:underline">{{
                $t('Open the full dashboard')
              }}</Link>
            </p>
          </aside>
        </div>
      </div>
    </main>
  </layout>
</template>

<style lang="scss" scoped>
@media (min-width: 1024px) {
  .today-grid {
    grid-template-columns: 1fr 340px;
  }
}
</style>
