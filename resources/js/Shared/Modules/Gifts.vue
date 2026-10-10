<script setup>
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Gift, ExternalLink, Check, Pencil, Trash2 } from 'lucide-vue-next';
import PrettyButton from '@/Shared/Form/PrettyButton.vue';
import PrettySpan from '@/Shared/Form/PrettySpan.vue';
import TextInput from '@/Shared/Form/TextInput.vue';
import TextArea from '@/Shared/Form/TextArea.vue';
import Dropdown from '@/Shared/Form/Dropdown.vue';
import Errors from '@/Shared/Form/Errors.vue';
import EmptyState from '@/Shared/EmptyState.vue';
import { flash } from '@/methods.js';

/**
 * Gifts: ideas and gifts for the contact (with occasion and progress), and
 * gifts received from them.
 */
const props = defineProps({
  data: Object,
});

const gifts = ref([...props.data.gifts]);
const formShown = ref(false);
const editedId = ref(null);
const loadingState = ref('');
const currencies = ref([]);

const blank = () => ({
  type: 'to_give',
  name: '',
  description: '',
  url: '',
  gift_occasion_id: '',
  gift_state_id: props.data.states[0]?.id ?? '',
  estimated_price: '',
  currency_id: '',
  given_at: '',
  received_at: '',
  errors: [],
});
const form = ref(blank());

const pending = computed(() => gifts.value.filter((gift) => gift.type === 'to_give' && !gift.given_at));
const given = computed(() => gifts.value.filter((gift) => gift.type === 'to_give' && gift.given_at));
const received = computed(() => gifts.value.filter((gift) => gift.type === 'received'));

const occasionOptions = computed(() => [
  { id: '', name: trans('No occasion') },
  ...props.data.occasions.map((o) => ({ id: o.id, name: o.label })),
]);
const stateOptions = computed(() => props.data.states.map((s) => ({ id: s.id, name: s.label })));
const currencyOptions = computed(() => [
  { id: '', name: trans('No currency') },
  ...currencies.value.map((c) => ({ id: c.id, name: c.code })),
]);

const loadCurrencies = () => {
  if (currencies.value.length > 0) return;
  axios.get(props.data.url.currencies).then((response) => {
    currencies.value = response.data.data;
  });
};

const showCreate = (type = 'to_give') => {
  form.value = { ...blank(), type };
  editedId.value = null;
  formShown.value = true;
  loadCurrencies();
};

const showEdit = (gift) => {
  form.value = {
    type: gift.type,
    name: gift.name,
    description: gift.description ?? '',
    url: gift.url_link ?? '',
    gift_occasion_id: gift.occasion?.id ?? '',
    gift_state_id: gift.state?.id ?? '',
    estimated_price: gift.estimated_price_input ?? '',
    currency_id: gift.currency_id ?? '',
    given_at: gift.given_at ?? '',
    received_at: gift.received_at ?? '',
    errors: [],
  };
  editedId.value = gift.id;
  formShown.value = true;
  loadCurrencies();
};

const cancel = () => {
  formShown.value = false;
  editedId.value = null;
};

const payload = () => ({
  ...form.value,
  gift_occasion_id: form.value.gift_occasion_id || null,
  gift_state_id: form.value.type === 'to_give' ? form.value.gift_state_id || null : null,
  currency_id: form.value.currency_id || null,
  given_at: form.value.type === 'to_give' ? form.value.given_at || null : null,
  received_at: form.value.type === 'received' ? form.value.received_at || null : null,
});

const submit = () => {
  loadingState.value = 'loading';
  const request = editedId.value
    ? axios.put(gifts.value.find((g) => g.id === editedId.value).url.update, payload())
    : axios.post(props.data.url.store, payload());

  request
    .then((response) => {
      loadingState.value = '';
      if (editedId.value) {
        gifts.value = gifts.value.map((gift) => (gift.id === editedId.value ? response.data.data : gift));
        flash(trans('The gift has been updated'), 'success');
      } else {
        gifts.value.unshift(response.data.data);
        flash(trans('The gift has been added'), 'success');
      }
      cancel();
    })
    .catch((error) => {
      loadingState.value = '';
      form.value.errors = error.response.data;
    });
};

// Mark a pending gift as given today, keeping everything else.
const markGiven = (gift) => {
  const lastState = props.data.states[props.data.states.length - 1];
  axios
    .put(gift.url.update, {
      type: gift.type,
      name: gift.name,
      description: gift.description,
      url: gift.url_link,
      gift_occasion_id: gift.occasion?.id ?? null,
      gift_state_id: lastState?.id ?? gift.state?.id ?? null,
      estimated_price: gift.estimated_price_input,
      currency_id: gift.currency_id,
      given_at: props.data.current_date,
    })
    .then((response) => {
      gifts.value = gifts.value.map((g) => (g.id === gift.id ? response.data.data : g));
      flash(trans('Marked as given. Lucky them!'), 'success');
    });
};

const destroy = (gift) => {
  if (!confirm(trans('Are you sure? This action cannot be undone.'))) return;
  axios.delete(gift.url.destroy).then(() => {
    gifts.value = gifts.value.filter((g) => g.id !== gift.id);
    flash(trans('The gift has been deleted'), 'success');
  });
};

const progress = (gift) => {
  if (!gift.state || props.data.states.length === 0) return 0;
  return Math.round((gift.state.position / props.data.states.length) * 100);
};
</script>

<template>
  <div class="mb-10">
    <!-- title + cta -->
    <div class="mb-3 items-center justify-between border-b border-gray-200 pb-2 dark:border-gray-700 sm:flex">
      <div class="mb-2 flex items-center gap-2 sm:mb-0">
        <Gift class="h-4 w-4 text-gray-600" />
        <span class="font-semibold">{{ $t('Gifts') }}</span>
      </div>
      <div class="flex gap-2">
        <pretty-button
          :text="$t('Add a gift idea')"
          :icon="'plus'"
          :class="'w-full sm:w-fit'"
          @click="showCreate('to_give')" />
        <pretty-span :text="$t('Received a gift')" @click="showCreate('received')" />
      </div>
    </div>

    <!-- form -->
    <form
      v-if="formShown"
      class="mb-6 rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
      @submit.prevent="submit">
      <div class="border-b border-gray-200 p-5 dark:border-gray-700">
        <errors :errors="form.errors" />

        <!-- type -->
        <div class="mb-4 inline-flex rounded-md border border-line p-0.5 text-sm">
          <button
            type="button"
            :class="form.type === 'to_give' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
            class="rounded-sm px-3 py-1 transition-colors"
            @click="form.type = 'to_give'">
            {{ $t('For them') }}
          </button>
          <button
            type="button"
            :class="form.type === 'received' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
            class="rounded-sm px-3 py-1 transition-colors"
            @click="form.type = 'received'">
            {{ $t('Received from them') }}
          </button>
        </div>

        <text-input
          v-model="form.name"
          :label="$t('What is it?')"
          :type="'text'"
          :input-class="'block w-full mb-4'"
          :required="true"
          :autofocus="true"
          :maxlength="255" />

        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <dropdown
            v-model="form.gift_occasion_id"
            :data="occasionOptions"
            :required="false"
            :label="$t('Occasion')"
            :dropdown-class="'block w-full'" />
          <dropdown
            v-if="form.type === 'to_give'"
            v-model="form.gift_state_id"
            :data="stateOptions"
            :required="false"
            :label="$t('Progress')"
            :dropdown-class="'block w-full'" />
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <text-input
            v-model="form.estimated_price"
            :label="$t('Estimated price')"
            :type="'text'"
            :input-class="'block w-full'"
            :required="false" />
          <dropdown
            v-model="form.currency_id"
            :data="currencyOptions"
            :required="false"
            :label="$t('Currency')"
            :dropdown-class="'block w-full'" />
          <div>
            <label class="mb-2 block text-sm" for="gift-date">{{
              form.type === 'to_give' ? $t('Given on') : $t('Received on')
            }}</label>
            <input
              id="gift-date"
              v-model="form[form.type === 'to_give' ? 'given_at' : 'received_at']"
              type="date"
              class="w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm shadow-xs focus:border-accent focus:ring-3 focus:ring-accent-soft dark:border-gray-700 dark:bg-slate-900" />
          </div>
        </div>

        <text-input
          v-model="form.url"
          :label="$t('Link')"
          :type="'url'"
          :input-class="'block w-full mb-4'"
          :required="false"
          :maxlength="255" />

        <text-area
          v-model="form.description"
          :label="$t('Notes')"
          :rows="3"
          :required="false"
          :maxlength="65535"
          :textarea-class="'block w-full'" />
      </div>

      <div class="flex justify-between p-5">
        <pretty-span :text="$t('Cancel')" :class="'me-3'" @click="cancel" />
        <pretty-button
          :text="editedId ? $t('Update') : $t('Save')"
          :state="loadingState"
          :icon="'check'"
          :class="'save'" />
      </div>
    </form>

    <!-- lists -->
    <div v-if="gifts.length > 0" class="space-y-6">
      <div v-if="pending.length">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
          {{ $t('Ideas and in progress') }}
        </p>
        <ul class="rounded-lg border border-line bg-surface-card">
          <li
            v-for="gift in pending"
            :key="gift.id"
            class="flex items-start gap-3 border-b border-line p-3 last:border-0">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-ink">{{ gift.name }}</span>
                <span
                  v-if="gift.occasion"
                  class="rounded-full bg-surface-sunken px-2 py-0.5 text-[11px] text-ink-muted"
                  >{{ gift.occasion.label }}</span
                >
                <a
                  v-if="gift.url_link"
                  :href="gift.url_link"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-ink-subtle hover:text-accent"
                  :title="gift.url_link"
                  ><ExternalLink class="h-3.5 w-3.5"
                /></a>
              </div>
              <p v-if="gift.description" class="text-sm text-ink-muted">{{ gift.description }}</p>
              <div class="mt-2 flex items-center gap-3 text-xs text-ink-muted">
                <span v-if="gift.state" class="flex items-center gap-2">
                  <span class="h-1.5 w-20 overflow-hidden rounded-full bg-surface-sunken"
                    ><span class="block h-full rounded-full bg-accent" :style="{ width: `${progress(gift)}%` }"></span
                  ></span>
                  {{ gift.state.label }}
                </span>
                <span v-if="gift.estimated_price" class="tabular">{{ gift.estimated_price }}</span>
              </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button type="button" class="gift-action" :title="$t('Mark as given')" @click="markGiven(gift)">
                <Check class="h-4 w-4" />
              </button>
              <button type="button" class="gift-action" :title="$t('Edit')" @click="showEdit(gift)">
                <Pencil class="h-4 w-4" />
              </button>
              <button type="button" class="gift-action hover:text-red-600" :title="$t('Delete')" @click="destroy(gift)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="given.length">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ $t('Given') }}</p>
        <ul class="rounded-lg border border-line bg-surface-card">
          <li
            v-for="gift in given"
            :key="gift.id"
            class="flex items-start gap-3 border-b border-line p-3 last:border-0">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-ink">{{ gift.name }}</span>
                <span
                  v-if="gift.occasion"
                  class="rounded-full bg-surface-sunken px-2 py-0.5 text-[11px] text-ink-muted"
                  >{{ gift.occasion.label }}</span
                >
              </div>
              <p class="text-xs text-ink-muted">
                {{ $t('Given on :date', { date: gift.given_at_human_format })
                }}<span v-if="gift.estimated_price" class="tabular"> · {{ gift.estimated_price }}</span>
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button type="button" class="gift-action" :title="$t('Edit')" @click="showEdit(gift)">
                <Pencil class="h-4 w-4" />
              </button>
              <button type="button" class="gift-action hover:text-red-600" :title="$t('Delete')" @click="destroy(gift)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="received.length">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ $t('Received from them') }}</p>
        <ul class="rounded-lg border border-line bg-surface-card">
          <li
            v-for="gift in received"
            :key="gift.id"
            class="flex items-start gap-3 border-b border-line p-3 last:border-0">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-ink">{{ gift.name }}</span>
                <span
                  v-if="gift.occasion"
                  class="rounded-full bg-surface-sunken px-2 py-0.5 text-[11px] text-ink-muted"
                  >{{ gift.occasion.label }}</span
                >
              </div>
              <p v-if="gift.description" class="text-sm text-ink-muted">{{ gift.description }}</p>
              <p v-if="gift.received_at_human_format" class="text-xs text-ink-muted">
                {{ $t('Received on :date', { date: gift.received_at_human_format }) }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button type="button" class="gift-action" :title="$t('Edit')" @click="showEdit(gift)">
                <Pencil class="h-4 w-4" />
              </button>
              <button type="button" class="gift-action hover:text-red-600" :title="$t('Delete')" @click="destroy(gift)">
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- blank state -->
    <empty-state
      v-if="gifts.length === 0 && !formShown"
      :icon="Gift"
      :title="$t('No gifts yet')"
      :description="$t('Keep ideas for the next occasion and remember what you gave and received.')"
      :compact="true" />
  </div>
</template>

<style scoped>
@reference '../../../css/app.css';

.gift-action {
  @apply flex h-7 w-7 items-center justify-center rounded-sm text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink;
}
</style>
