<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import emitter from 'tiny-emitter/instance';
import { Plus, NotebookPen, Phone, SquareCheckBig, BellRing, X } from 'lucide-vue-next';
import ContactSelector from '@/Shared/Form/ContactSelector.vue';
import TextArea from '@/Shared/Form/TextArea.vue';
import PrettyButton from '@/Shared/Form/PrettyButton.vue';
import PrettySpan from '@/Shared/Form/PrettySpan.vue';
import Errors from '@/Shared/Form/Errors.vue';
import { flash } from '@/methods.js';

/**
 * Quick capture: write something about one or more people from anywhere in the vault.
 * Opens with the floating button, the `n` key, or the global `quick-capture:open` event
 * (payload: { type, contacts, body, date }).
 */
const props = defineProps({
  layoutData: Object,
});

const open = ref(false);
const saving = ref(false);
const editor = useTemplateRef('editor');
const form = ref({
  type: 'note',
  contacts: [],
  body: '',
  date: '',
  errors: [],
});

const vault = computed(() => props.layoutData?.vault ?? null);
const canCapture = computed(() => Boolean(vault.value?.permission?.at_least_editor && vault.value?.url?.quick_capture));

const types = computed(() => [
  { id: 'note', label: trans('Note'), icon: NotebookPen, hint: trans('Something you want to remember about them.') },
  { id: 'call', label: trans('Call'), icon: Phone, hint: trans('You talked on the phone or in a video call.') },
  { id: 'task', label: trans('Task'), icon: SquareCheckBig, hint: trans('Something you need to do for them.') },
  { id: 'reminder', label: trans('Reminder'), icon: BellRing, hint: trans('Monica will remind you on that day.') },
]);

const currentType = computed(() => types.value.find((type) => type.id === form.value.type) ?? types.value[0]);
const needsDate = computed(() => form.value.type !== 'note');
const dateLabel = computed(
  () =>
    ({ call: trans('Called on'), task: trans('Due on'), reminder: trans('Remind me on') })[form.value.type] ??
    trans('Date'),
);
const placeholder = computed(
  () =>
    ({
      note: trans('What happened? Type @ to mention someone, / for a template.'),
      call: trans('What did you talk about?'),
      task: trans('First line is the task, the rest is the description.'),
      reminder: trans('What should Monica remind you about?'),
    })[form.value.type],
);
const canSubmit = computed(() => form.value.contacts.length > 0 && form.value.body.trim() !== '' && !saving.value);

const today = () => {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};

const show = (payload = {}) => {
  if (!canCapture.value) {
    return;
  }

  form.value = {
    type: payload.type ?? 'note',
    contacts: payload.contacts ?? [],
    body: payload.body ?? '',
    date: payload.date ?? today(),
    errors: [],
  };
  open.value = true;
  nextTick(() => editor.value?.focus());
};

const hide = () => {
  open.value = false;
};

const isTyping = (event) => {
  const target = event.target;
  return target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
};

const onKeydown = (event) => {
  if (event.key === 'Escape' && open.value) {
    hide();
    return;
  }

  if (event.key === 'n' && !open.value && !event.metaKey && !event.ctrlKey && !event.altKey && !isTyping(event)) {
    event.preventDefault();
    show();
  }
};

onMounted(() => {
  emitter.on('quick-capture:open', show);
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  emitter.off('quick-capture:open', show);
  window.removeEventListener('keydown', onKeydown);
});

const submit = () => {
  if (!canSubmit.value) {
    return;
  }

  saving.value = true;

  axios
    .post(vault.value.url.quick_capture, {
      type: form.value.type,
      contact_ids: form.value.contacts.map((contact) => contact.id),
      body: form.value.body,
      date: needsDate.value ? form.value.date : null,
    })
    .then((response) => {
      saving.value = false;
      const contacts = response.data.data.contacts;
      flash(
        trans(':type saved for :names', {
          type: currentType.value.label,
          names: contacts.map((contact) => contact.name).join(', '),
        }),
        'success',
      );
      emitter.emit('quick-capture:saved', response.data.data);
      // an empty body also clears the browser draft
      form.value.body = '';
      open.value = false;

      if (contacts.some((contact) => window.location.pathname.includes(contact.id))) {
        router.reload({ preserveScroll: true });
      }
    })
    .catch((error) => {
      saving.value = false;
      form.value.errors = error.response?.data ?? {};
    });
};
</script>

<template>
  <template v-if="canCapture">
    <!-- floating button -->
    <button
      v-show="!open"
      type="button"
      class="group fixed bottom-5 end-5 z-40 flex h-12 items-center gap-2 rounded-full bg-accent pe-4 ps-3 text-accent-contrast shadow-[0_6px_20px_oklch(0_0_0/0.18)] transition-[transform,box-shadow] hover:shadow-[0_10px_28px_oklch(0_0_0/0.22)] active:scale-[0.96] print:hidden"
      :title="$t('Quick capture (N)')"
      :aria-label="$t('Quick capture (N)')"
      @click="show()">
      <Plus class="h-5 w-5" />
      <span class="hidden text-sm font-medium sm:inline">{{ $t('Capture') }}</span>
      <kbd
        class="hidden rounded-sm border border-white/30 px-1 font-sans text-[10px] leading-4 opacity-80 sm:inline"
        aria-hidden="true"
        >N</kbd
      >
    </button>

    <!-- panel -->
    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
        role="dialog"
        aria-modal="true">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="hide"></div>

        <form
          class="relative flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl border border-line bg-surface-card shadow-2xl sm:rounded-2xl"
          @submit.prevent="submit">
          <!-- header -->
          <div class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="font-semibold text-ink">{{ $t('What do you want to remember?') }}</h2>
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full text-ink-muted hover:bg-surface-sunken hover:text-ink"
              :aria-label="$t('Close')"
              @click="hide">
              <X class="h-4 w-4" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-4">
            <errors :errors="form.errors" />

            <!-- type -->
            <div class="mb-4 grid grid-cols-4 gap-2" role="radiogroup" :aria-label="$t('Type')">
              <button
                v-for="type in types"
                :key="type.id"
                type="button"
                role="radio"
                :aria-checked="form.type === type.id"
                :title="type.hint"
                :class="
                  form.type === type.id
                    ? 'border-accent bg-accent-soft text-ink ring-1 ring-accent'
                    : 'border-line text-ink-muted hover:border-line-strong hover:text-ink'
                "
                class="flex flex-col items-center gap-1 rounded-lg border px-2 py-2 text-xs font-medium transition-[border-color,background-color,color,transform] active:scale-[0.96]"
                @click="form.type = type.id">
                <component :is="type.icon" class="h-4 w-4" />
                {{ type.label }}
              </button>
            </div>
            <p class="mb-4 text-xs text-ink-muted">{{ currentType.hint }}</p>

            <!-- contacts -->
            <contact-selector
              v-model="form.contacts"
              :label="$t('Who is this about?')"
              :search-url="vault.url.search_contacts_only"
              :most-consulted-contacts-url="vault.url.get_most_consulted_contacts"
              :display-most-consulted-contacts="true"
              :add-multiple-contacts="true"
              :required="true"
              class="mb-4" />

            <!-- body -->
            <text-area
              ref="editor"
              v-model="form.body"
              :id="'quick-capture-body'"
              :rows="5"
              :required="true"
              :maxlength="65535"
              :markdown="true"
              :mention-url="vault.url.search_contacts_only"
              :draft-key="`quick.${vault.id}`"
              :placeholder="placeholder"
              :textarea-class="'block w-full'"
              @esc-key-pressed="hide" />

            <!-- date -->
            <div v-if="needsDate" class="mt-4">
              <label for="quick-capture-date" class="mb-2 block text-sm">{{ dateLabel }}</label>
              <input
                id="quick-capture-date"
                v-model="form.date"
                type="date"
                class="rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm shadow-xs focus:border-accent focus:ring-3 focus:ring-accent-soft dark:border-gray-700 dark:bg-slate-900" />
            </div>
          </div>

          <!-- footer -->
          <div class="flex items-center justify-between border-t border-line bg-surface-raised px-5 py-3">
            <pretty-span :text="$t('Cancel')" @click="hide" />
            <pretty-button
              :text="$t('Save')"
              :icon="'check'"
              :class="'save'"
              :state="saving ? 'loading' : canSubmit ? '' : 'disabled'" />
          </div>
        </form>
      </div>
    </Teleport>
  </template>
</template>
