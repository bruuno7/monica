<script setup>
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { HeartHandshake, Check } from 'lucide-vue-next';
import { flash } from '@/methods.js';

/**
 * "Stay in touch" goal of a contact: how often the user wants to interact, how long
 * ago something last happened, and a one-click "we talked today".
 */
const props = defineProps({
  data: Object,
  editable: { type: Boolean, default: true },
});

const local = ref({ ...props.data });
const saving = ref(false);
const touching = ref(false);

const statusStyles = {
  off: 'bg-surface-sunken text-ink-muted',
  fresh: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
  cooling: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  cold: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
};

const dotStyles = {
  off: 'bg-ink-subtle',
  fresh: 'bg-emerald-500',
  cooling: 'bg-amber-500',
  cold: 'bg-rose-500',
};

const lastInteraction = computed(() => {
  const days = local.value.days_since_last_interaction;
  if (days === null || days === undefined) {
    return trans('No interaction recorded yet');
  }
  if (days === 0) {
    return trans('Last interaction: today');
  }
  if (days === 1) {
    return trans('Last interaction: yesterday');
  }
  return trans('Last interaction: :count days ago', { count: days });
});

const updateFrequency = (event) => {
  saving.value = true;
  const value = event.target.value;

  axios
    .put(local.value.url.update, { frequency: value === '' ? null : Number(value) })
    .then((response) => {
      local.value = response.data.data;
      saving.value = false;
      flash(trans('Changes saved'), 'success');
    })
    .catch(() => {
      saving.value = false;
    });
};

const touch = () => {
  touching.value = true;

  axios
    .post(local.value.url.touch)
    .then((response) => {
      local.value = response.data.data;
      touching.value = false;
      flash(trans('Great! Interaction recorded for today.'), 'success');
    })
    .catch(() => {
      touching.value = false;
    });
};
</script>

<template>
  <div class="mb-8 rounded-lg border border-line bg-surface-card p-3 text-sm">
    <div class="mb-2 flex items-center gap-2 font-semibold text-ink">
      <HeartHandshake class="h-4 w-4 text-ink-muted" />
      {{ $t('Stay in touch') }}
    </div>

    <!-- status -->
    <div
      class="mb-2 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
      :class="statusStyles[local.status]">
      <span class="h-1.5 w-1.5 rounded-full" :class="dotStyles[local.status]"></span>
      {{ local.status === 'off' ? $t('No goal') : local.status_label }}
    </div>

    <p class="mb-3 text-xs text-ink-muted tabular">{{ lastInteraction }}</p>

    <template v-if="editable">
      <label class="mb-1 block text-xs text-ink-muted" for="stay-in-touch-frequency">{{
        $t('I want to be in touch')
      }}</label>
      <select
        id="stay-in-touch-frequency"
        :value="local.frequency ?? ''"
        :disabled="saving"
        class="mb-3 w-full rounded-md border-gray-300 bg-white py-1.5 text-sm focus:border-accent focus:ring-accent dark:border-gray-700 dark:bg-slate-900"
        @change="updateFrequency">
        <option value="">{{ $t('No goal') }}</option>
        <option v-for="frequency in local.frequencies" :key="frequency.id" :value="frequency.id">
          {{ frequency.label }}
        </option>
      </select>

      <button
        type="button"
        :disabled="touching"
        class="flex w-full items-center justify-center gap-1.5 rounded-md border border-line bg-surface px-2 py-1.5 text-xs font-medium text-ink transition-[background-color,transform] hover:bg-surface-sunken active:scale-[0.96] disabled:opacity-50"
        @click="touch">
        <Check class="h-3.5 w-3.5" />
        {{ $t('We talked today') }}
      </button>
    </template>
  </div>
</template>
