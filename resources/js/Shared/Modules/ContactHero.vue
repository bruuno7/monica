<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';
import { Pencil, Star, NotebookPen, Phone, SquareCheckBig, BellRing, Check } from 'lucide-vue-next';
import Avatar from '@/Shared/Avatar.vue';
import { flash } from '@/methods.js';

/**
 * The top of a contact profile: who this person is at a glance, how the
 * relationship is doing, and the actions used every day.
 */
const props = defineProps({
  data: Object,
  stayInTouch: Object,
  contactUrl: String,
  contactId: String,
  editable: { type: Boolean, default: true },
});

const favorite = ref(props.data.is_favorite);
const sit = ref({ ...props.stayInTouch });
const touching = ref(false);

const statusStyles = {
  fresh: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
  cooling: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  cold: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
};

const subtitle = computed(() =>
  [
    props.data.age ? trans(':count years old', { count: props.data.age }) : null,
    props.data.pronoun,
    props.data.job || null,
  ]
    .filter(Boolean)
    .join(' · '),
);

const lastInteraction = computed(() => {
  const days = sit.value.days_since_last_interaction;
  if (days === null || days === undefined) return trans('No interaction recorded yet');
  if (days === 0) return trans('You were in touch today');
  if (days === 1) return trans('You were in touch yesterday');
  return trans('Last interaction :count days ago', { count: days });
});

const toggleFavorite = () => {
  axios.put(props.data.url.toggle_favorite).then(() => {
    favorite.value = !favorite.value;
    flash(trans('Changes saved'), 'success');
  });
};

const contact = () => ({ id: props.contactId, name: props.data.name, url: props.contactUrl });

const capture = (type) => {
  emitter.emit('quick-capture:open', { type, contacts: [contact()] });
};

const talked = () => {
  touching.value = true;
  axios
    .post(sit.value.url.touch)
    .then((response) => {
      sit.value = response.data.data;
      touching.value = false;
      flash(trans('Great! Interaction recorded for today.'), 'success');
    })
    .catch(() => {
      touching.value = false;
    });
};
</script>

<template>
  <section class="relative mb-8 overflow-hidden rounded-xl border border-line bg-surface-card">
    <!-- soft accent banner -->
    <div class="h-16 bg-gradient-to-r from-accent-soft via-surface-raised to-accent-soft sm:h-20"></div>

    <div class="px-5 pb-5">
      <div class="-mt-10 flex flex-col gap-4 sm:-mt-12 sm:flex-row sm:items-end">
        <avatar
          :data="data.avatar"
          :class="'h-20 w-20 shrink-0 overflow-hidden rounded-full border-4 border-surface-card bg-surface-card sm:h-24 sm:w-24'"
          :img-classes="'h-full w-full rounded-full object-cover'" />

        <div class="min-w-0 flex-1 sm:pb-1">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="truncate text-2xl font-bold text-ink">{{ data.name }}</h1>
            <button
              type="button"
              :title="favorite ? $t('Remove from favorites') : $t('Add to favorites')"
              :aria-label="favorite ? $t('Remove from favorites') : $t('Add to favorites')"
              class="flex h-7 w-7 items-center justify-center rounded-full text-ink-subtle transition-[color,transform] hover:text-amber-500 active:scale-[0.96]"
              @click="toggleFavorite">
              <Star class="h-4 w-4" :class="favorite ? 'fill-amber-400 text-amber-500' : ''" />
            </button>
            <Link v-if="editable" :href="data.url.edit" class="text-ink-subtle hover:text-ink" :title="$t('Edit')">
              <Pencil class="h-3.5 w-3.5" />
            </Link>
          </div>
          <p v-if="subtitle" class="truncate text-sm text-ink-muted">{{ subtitle }}</p>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span
              v-for="label in data.labels"
              :key="label.id"
              class="rounded-full px-2 py-0.5 text-[11px] font-medium leading-4"
              :class="label.bg_color + ' ' + label.text_color">
              {{ label.name }}
            </span>
            <span
              v-if="sit.status !== 'off'"
              class="rounded-full px-2 py-0.5 text-[11px] font-medium leading-4"
              :class="statusStyles[sit.status]">
              {{ sit.status_label }}
            </span>
            <span class="text-xs text-ink-muted tabular">{{ lastInteraction }}</span>
          </div>
        </div>
      </div>

      <!-- quick actions -->
      <div v-if="editable" class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
        <button type="button" class="hero-action" @click="capture('note')">
          <NotebookPen class="h-4 w-4" /> {{ $t('Write a note') }}
        </button>
        <button type="button" class="hero-action" @click="capture('call')">
          <Phone class="h-4 w-4" /> {{ $t('Log a call') }}
        </button>
        <button type="button" class="hero-action" @click="capture('task')">
          <SquareCheckBig class="h-4 w-4" /> {{ $t('Add a task') }}
        </button>
        <button type="button" class="hero-action" @click="capture('reminder')">
          <BellRing class="h-4 w-4" /> {{ $t('Remind me') }}
        </button>
        <button type="button" class="hero-action ms-auto" :disabled="touching" @click="talked">
          <Check class="h-4 w-4" /> {{ $t('We talked today') }}
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
@reference '../../../css/app.css';

.hero-action {
  @apply flex items-center gap-1.5 rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium text-ink transition-[background-color,border-color,transform] hover:border-accent hover:bg-accent-soft active:scale-[0.96] disabled:opacity-50;
}
</style>
