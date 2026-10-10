<script>
export default {
  inheritAttrs: false,
};
</script>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { debounce } from 'lodash';
import {
  Bold,
  Italic,
  Heading2,
  List,
  ListOrdered,
  Quote,
  Link2,
  Eye,
  PenLine,
  AtSign,
  SlashSquare,
} from 'lucide-vue-next';

const props = defineProps({
  id: {
    type: String,
    default: 'text-area-',
  },
  type: {
    type: String,
    default: 'text',
  },
  textareaClass: {
    type: String,
    default: '',
  },
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: Number,
    default: 3,
  },
  autofocus: {
    type: Boolean,
    default: false,
  },
  maxlength: {
    type: Number,
    default: null,
  },
  markdown: {
    type: Boolean,
    default: false,
  },
  // Endpoint used to look up contacts when the user types `@`. Enables mentions.
  mentionUrl: {
    type: String,
    default: null,
  },
  // When set, what is typed is kept in the browser until it is saved, so nothing is lost.
  draftKey: {
    type: String,
    default: null,
  },
  placeholder: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['esc-key-pressed', 'update:modelValue']);

const displayMaxLength = ref(false);
const zone = useTemplateRef('zone');
const mode = ref('write');
const previewHtml = ref('');
const previewLoading = ref(false);
const draftRestored = ref(false);

// popover for @mentions and /templates
const popover = ref({ type: null, items: [], index: 0, start: 0, end: 0, loading: false });

const proxyValue = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emit('update:modelValue', value);
  },
});

const charactersLeft = computed(() => {
  let char = 0;
  if (props.modelValue) {
    char = props.modelValue.length;
  }

  return `${props.maxlength - char} / ${props.maxlength}`;
});

const localTextAreaClasses = computed(() => [
  'bg-white dark:bg-slate-900 border-gray-300 dark:border-gray-700',
  'focus:border-accent focus:ring-3 focus:ring-accent-soft',
  props.markdown ? 'rounded-t-none rounded-b-md border-t-0 shadow-none' : 'rounded-md shadow-xs',
  props.textareaClass,
]);

const templates = computed(() => [
  {
    id: 'call',
    name: trans('Call'),
    hint: trans('Who called, what you talked about, next step'),
    body: `📞 ${trans('Call with')} \n\n**${trans('We talked about')}:**\n- \n\n**${trans('Next step')}:** `,
  },
  {
    id: 'meetup',
    name: trans('Meetup'),
    hint: trans('Where you met and the highlights'),
    body: `🍽️ ${trans('Met up at')} \n\n**${trans('Highlights')}:**\n- \n- `,
  },
  {
    id: 'news',
    name: trans('News'),
    hint: trans('Something new in their life'),
    body: `📰 ${trans('News')}: `,
  },
  {
    id: 'gift',
    name: trans('Gift idea'),
    hint: trans('An idea to remember for the next occasion'),
    body: `🎁 ${trans('Gift idea')}: `,
  },
  {
    id: 'thanks',
    name: trans('Gratitude'),
    hint: trans('Something you are grateful for'),
    body: `🙏 ${trans('Grateful for')}: `,
  },
]);

/* ------------------------------------------------------------------ */
/* basics                                                              */
/* ------------------------------------------------------------------ */

const sendEscKey = () => {
  if (popover.value.type) {
    closePopover();
    return;
  }
  emit('esc-key-pressed');
};

const showMaxLength = () => {
  displayMaxLength.value = true;
};

const focus = () => {
  zone.value?.focus();
};

const autosize = () => {
  const element = zone.value;
  if (!element) return;
  element.style.height = 'auto';
  element.style.height = `${Math.max(element.scrollHeight, props.rows * 24)}px`;
};

const setValue = (value, selectionStart = null, selectionEnd = null) => {
  emit('update:modelValue', value);
  nextTick(() => {
    autosize();
    if (selectionStart !== null && zone.value) {
      zone.value.focus();
      zone.value.setSelectionRange(selectionStart, selectionEnd ?? selectionStart);
    }
  });
};

/* ------------------------------------------------------------------ */
/* toolbar                                                             */
/* ------------------------------------------------------------------ */

const selection = () => {
  const element = zone.value;
  const value = props.modelValue ?? '';
  return {
    value,
    start: element?.selectionStart ?? value.length,
    end: element?.selectionEnd ?? value.length,
  };
};

// Wrap the selection with the given markers (or insert a placeholder).
const wrap = (before, after = before, placeholder = '') => {
  const { value, start, end } = selection();
  const selected = value.slice(start, end) || placeholder;
  const next = value.slice(0, start) + before + selected + after + value.slice(end);
  setValue(next, start + before.length, start + before.length + selected.length);
};

// Prefix each selected line (or the current line) with a marker.
const prefixLines = (prefix, ordered = false) => {
  const { value, start, end } = selection();
  const lineStart = value.lastIndexOf('\n', start - 1) + 1;
  const lineEndIndex = value.indexOf('\n', end);
  const lineEnd = lineEndIndex === -1 ? value.length : lineEndIndex;
  const lines = value.slice(lineStart, lineEnd).split('\n');
  const prefixed = lines.map((line, i) => `${ordered ? `${i + 1}. ` : prefix}${line}`).join('\n');
  const next = value.slice(0, lineStart) + prefixed + value.slice(lineEnd);
  setValue(next, lineStart, lineStart + prefixed.length);
};

const insertLink = () => {
  const { value, start, end } = selection();
  const selected = value.slice(start, end);
  const text = selected || trans('text');
  const next = `${value.slice(0, start)}[${text}](https://)${value.slice(end)}`;
  const urlStart = start + text.length + 3;
  setValue(next, urlStart, urlStart + 8);
};

const toolbar = computed(() => [
  { id: 'bold', icon: Bold, title: `${trans('Bold')} (⌘B)`, run: () => wrap('**', '**', trans('bold')) },
  { id: 'italic', icon: Italic, title: `${trans('Italic')} (⌘I)`, run: () => wrap('_', '_', trans('italic')) },
  { id: 'heading', icon: Heading2, title: trans('Heading'), run: () => prefixLines('## ') },
  { id: 'list', icon: List, title: trans('Bulleted list'), run: () => prefixLines('- ') },
  { id: 'ordered', icon: ListOrdered, title: trans('Numbered list'), run: () => prefixLines('', true) },
  { id: 'quote', icon: Quote, title: trans('Quote'), run: () => prefixLines('> ') },
  { id: 'link', icon: Link2, title: `${trans('Link')} (⌘K)`, run: insertLink },
]);

const onKeydown = (event) => {
  if (popover.value.type) {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      popover.value.index = (popover.value.index + 1) % Math.max(popover.value.items.length, 1);
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      popover.value.index =
        (popover.value.index - 1 + popover.value.items.length) % Math.max(popover.value.items.length, 1);
      return;
    }
    if ((event.key === 'Enter' || event.key === 'Tab') && popover.value.items.length > 0) {
      event.preventDefault();
      pick(popover.value.items[popover.value.index]);
      return;
    }
  }

  if (!props.markdown || !(event.metaKey || event.ctrlKey)) return;

  const key = event.key.toLowerCase();
  if (key === 'b') {
    event.preventDefault();
    wrap('**', '**', trans('bold'));
  } else if (key === 'i') {
    event.preventDefault();
    wrap('_', '_', trans('italic'));
  } else if (key === 'k') {
    event.preventDefault();
    insertLink();
  }
};

/* ------------------------------------------------------------------ */
/* @mentions and /templates                                            */
/* ------------------------------------------------------------------ */

const closePopover = () => {
  popover.value = { type: null, items: [], index: 0, start: 0, end: 0, loading: false };
};

const searchMentions = debounce((query) => {
  if (!props.mentionUrl) return;
  popover.value.loading = true;

  axios
    .post(props.mentionUrl, { searchTerm: query })
    .then((response) => {
      if (popover.value.type !== 'mention') return;
      popover.value.items = response.data.data.map((contact) => ({
        id: contact.id,
        name: contact.name.replace(/\s+/g, ' ').trim(),
        url: contact.url,
      }));
      popover.value.index = 0;
      popover.value.loading = false;
    })
    .catch(() => {
      popover.value.loading = false;
    });
}, 250);

const detectTriggers = () => {
  if (!props.markdown) return;
  const element = zone.value;
  if (!element) return;

  const caret = element.selectionStart;
  const before = (props.modelValue ?? '').slice(0, caret);

  const mention = props.mentionUrl ? before.match(/(?:^|\s)@([^\s@]{2,40})$/) : null;
  if (mention) {
    const start = caret - mention[1].length - 1;
    popover.value = { ...popover.value, type: 'mention', start, end: caret, loading: true };
    searchMentions(mention[1]);
    return;
  }

  const slash = before.match(/(?:^|\n)\/([a-z]{0,20})$/i);
  if (slash) {
    const query = slash[1].toLowerCase();
    const items = templates.value.filter((t) => t.name.toLowerCase().includes(query) || t.id.includes(query));
    popover.value = {
      type: 'template',
      items,
      index: 0,
      start: caret - slash[1].length - 1,
      end: caret,
      loading: false,
    };
    return;
  }

  if (popover.value.type) closePopover();
};

const pick = (item) => {
  const { value } = selection();
  const { start, end, type } = popover.value;
  const insert = type === 'mention' ? `[${item.name}](${item.url}) ` : item.body;
  const next = value.slice(0, start) + insert + value.slice(end);
  closePopover();
  setValue(next, start + insert.length);
};

const onInput = (event) => {
  emit('update:modelValue', event.target.value);
  autosize();
  nextTick(detectTriggers);
};

/* ------------------------------------------------------------------ */
/* preview                                                             */
/* ------------------------------------------------------------------ */

const showPreview = () => {
  mode.value = 'preview';
  previewLoading.value = true;

  axios
    .post(route('markdown.preview'), { content: props.modelValue })
    .then((response) => {
      previewHtml.value = response.data.data.html;
      previewLoading.value = false;
    })
    .catch(() => {
      previewLoading.value = false;
    });
};

const showWrite = () => {
  mode.value = 'write';
  nextTick(() => {
    autosize();
    focus();
  });
};

/* ------------------------------------------------------------------ */
/* drafts                                                              */
/* ------------------------------------------------------------------ */

const storageKey = computed(() => (props.draftKey ? `monica.draft.${props.draftKey}` : null));

const saveDraft = debounce((value) => {
  if (!storageKey.value) return;
  if (value && value.trim() !== '') {
    localStorage.setItem(storageKey.value, value);
  } else {
    localStorage.removeItem(storageKey.value);
  }
}, 400);

const discardDraft = () => {
  if (storageKey.value) localStorage.removeItem(storageKey.value);
  draftRestored.value = false;
  setValue('');
};

watch(
  () => props.modelValue,
  (value) => {
    if (!storageKey.value) return;
    if (!value || value.trim() === '') {
      saveDraft.cancel();
      localStorage.removeItem(storageKey.value);
      draftRestored.value = false;
    } else {
      saveDraft(value);
    }
  },
);

onMounted(() => {
  autosize();

  if (storageKey.value && (!props.modelValue || props.modelValue.trim() === '')) {
    const draft = localStorage.getItem(storageKey.value);
    if (draft) {
      draftRestored.value = true;
      setValue(draft);
    }
  }
});

onBeforeUnmount(() => {
  saveDraft.flush();
});

defineExpose({
  focus,
});
</script>

<template>
  <div>
    <label v-if="label" class="mb-2 block relative text-sm dark:text-gray-100" :for="id">
      {{ label }}
      <span v-if="!required" class="optional-badge rounded-xs px-[3px] py-px text-xs">
        {{ $t('optional') }}
      </span>

      <span
        v-if="maxlength && displayMaxLength"
        class="length tabular absolute end-0 top-0 rounded-xs px-1 py-[3px] text-xs dark:text-gray-100">
        {{ charactersLeft }}
      </span>
    </label>

    <!-- toolbar -->
    <div
      v-if="markdown"
      class="flex items-center justify-between gap-2 rounded-t-md border border-b-0 border-gray-300 bg-surface-raised px-1.5 py-1 dark:border-gray-700">
      <div class="flex items-center gap-0.5" role="toolbar" :aria-label="$t('Formatting')">
        <button
          v-for="tool in toolbar"
          :key="tool.id"
          type="button"
          :title="tool.title"
          :aria-label="tool.title"
          :disabled="mode === 'preview'"
          class="flex h-7 w-7 items-center justify-center rounded-sm text-ink-muted transition-[background-color,color,transform] hover:bg-surface-sunken hover:text-ink active:scale-[0.96] disabled:opacity-40"
          @mousedown.prevent
          @click="tool.run">
          <component :is="tool.icon" class="h-4 w-4" />
        </button>
      </div>

      <div class="flex items-center gap-1 text-xs">
        <span
          v-if="mentionUrl"
          class="hidden items-center gap-1 text-ink-subtle sm:flex"
          :title="$t('Type @ to mention a contact')">
          <AtSign class="h-3 w-3" />
        </span>
        <span class="hidden items-center gap-1 text-ink-subtle sm:flex" :title="$t('Type / for a template')">
          <SlashSquare class="h-3 w-3" />
        </span>
        <div class="ms-1 inline-flex rounded-sm border border-line p-0.5">
          <button
            type="button"
            :class="mode === 'write' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
            class="flex items-center gap-1 rounded-xs px-2 py-0.5 transition-colors"
            @click="showWrite">
            <PenLine class="h-3 w-3" />
            {{ $t('Write') }}
          </button>
          <button
            type="button"
            :class="mode === 'preview' ? 'bg-accent text-accent-contrast' : 'text-ink-muted hover:text-ink'"
            class="flex items-center gap-1 rounded-xs px-2 py-0.5 transition-colors"
            @click="showPreview">
            <Eye class="h-3 w-3" />
            {{ $t('Preview') }}
          </button>
        </div>
      </div>
    </div>

    <div class="relative">
      <textarea
        v-show="mode === 'write'"
        :id="id"
        v-model="proxyValue"
        :class="localTextAreaClasses"
        :required="required"
        :type="type"
        :autofocus="autofocus"
        :rows="rows"
        :placeholder="placeholder"
        ref="zone"
        :maxlength="maxlength"
        @input="onInput"
        @click="detectTriggers"
        @keyup.left="detectTriggers"
        @keyup.right="detectTriggers"
        @keydown="onKeydown"
        @keydown.esc="sendEscKey"
        @focus="showMaxLength"
        @blur="displayMaxLength = false" />

      <!-- preview -->
      <div
        v-if="mode === 'preview'"
        class="min-h-24 rounded-b-md border border-t-0 border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-slate-900"
        :style="{ minHeight: `${rows * 24}px` }">
        <p v-if="previewLoading" class="text-sm text-ink-muted">{{ $t('Loading…') }}</p>
        <div v-else-if="previewHtml" class="prose-note" v-html="previewHtml"></div>
        <p v-else class="text-sm text-ink-muted">{{ $t('Nothing to preview yet.') }}</p>
      </div>

      <!-- @mentions / templates -->
      <div
        v-if="popover.type && (popover.items.length > 0 || popover.loading)"
        class="absolute start-2 top-full z-20 mt-1 w-72 overflow-hidden rounded-md border border-line bg-surface-card shadow-lg"
        role="listbox">
        <p v-if="popover.loading && popover.items.length === 0" class="px-3 py-2 text-xs text-ink-muted">
          {{ $t('Loading…') }}
        </p>
        <button
          v-for="(item, index) in popover.items"
          :key="item.id"
          type="button"
          role="option"
          :aria-selected="index === popover.index"
          :class="index === popover.index ? 'bg-accent-soft' : ''"
          class="flex w-full flex-col items-start px-3 py-1.5 text-start text-sm hover:bg-accent-soft"
          @mousedown.prevent
          @click="pick(item)">
          <span class="font-medium text-ink">{{ item.name }}</span>
          <span v-if="item.hint" class="text-xs text-ink-muted">{{ item.hint }}</span>
        </button>
      </div>
    </div>

    <div
      v-if="markdown"
      class="flex flex-wrap items-center justify-between gap-2 rounded-b-lg bg-slate-100 px-3 py-2 text-xs dark:bg-slate-900">
      <span>
        {{ $t('We support Markdown to format the text (bold, lists, headings, etc…).') }}
        <a
          href="https://github.com/adam-p/markdown-here/wiki/Markdown-Cheatsheet"
          target="_blank"
          lang="en"
          rel="noopener noreferrer"
          class="ms-1 text-accent hover:underline">
          {{ $t('(Help)') }}
        </a>
      </span>

      <span v-if="draftRestored" class="flex items-center gap-2 text-ink-muted">
        {{ $t('Draft restored') }}
        <button type="button" class="text-accent hover:underline" @click="discardDraft">{{ $t('Discard') }}</button>
      </span>
    </div>

    <p v-if="help" class="f7 mb3 lh-title">
      {{ help }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.optional-badge {
  color: #283e59;
  background-color: #edf2f9;
}
.dark .optional-badge {
  color: #d4d8dd !important;
  background-color: #2f3031 !important;
}

.length {
  background-color: #e5eeff;
}
.dark .length {
  background-color: #2d2f33 !important;
}

textarea {
  resize: vertical;
}
</style>
