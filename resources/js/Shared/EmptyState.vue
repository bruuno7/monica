<script setup>
/**
 * Friendly empty state: an illustration (or icon), a short title, a hint and an optional action.
 */
defineProps({
  // Path of an illustration, e.g. /img/dashboard_blank_reminders.svg
  image: { type: String, default: null },
  // A Lucide icon component, used when there is no illustration.
  icon: { type: [Object, Function], default: null },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="flex flex-col items-center rounded-lg border border-dashed border-line-strong bg-surface-card text-center"
    :class="compact ? 'gap-1 px-4 py-4' : 'gap-2 px-6 py-8'">
    <img v-if="image" :src="image" alt="" class="mb-1 h-16 w-16 opacity-90" />
    <span
      v-else-if="icon"
      class="mb-1 flex h-10 w-10 items-center justify-center rounded-full bg-accent-soft text-accent">
      <component :is="icon" class="h-5 w-5" />
    </span>

    <p v-if="title" class="font-medium text-ink">{{ title }}</p>
    <p v-if="description" class="max-w-prose text-sm text-ink-muted">{{ description }}</p>

    <div v-if="$slots.default" class="mt-2">
      <slot />
    </div>
  </div>
</template>
