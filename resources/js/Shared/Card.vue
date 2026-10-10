<script setup>
/**
 * The standard container of the app: a bordered surface with optional header and footer.
 * Nested radii stay concentric (outer `rounded-lg` = inner `rounded-md` + padding).
 */
defineProps({
  // Remove the inner padding, for lists that manage their own spacing.
  flush: { type: Boolean, default: false },
  // Dashed border for optional/empty content.
  dashed: { type: Boolean, default: false },
  // Lift the card with a soft shadow instead of relying on the border only.
  elevated: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="rounded-lg border bg-surface-card text-ink"
    :class="[
      dashed ? 'border-dashed border-line-strong' : 'border-line',
      elevated ? 'shadow-[0_1px_2px_oklch(0_0_0/0.04),0_4px_12px_oklch(0_0_0/0.06)] dark:shadow-none' : '',
    ]">
    <div v-if="$slots.header" class="flex items-center justify-between gap-3 border-b border-line px-4 py-2">
      <slot name="header" />
    </div>

    <div :class="flush ? '' : 'p-4'">
      <slot />
    </div>

    <div v-if="$slots.footer" class="rounded-b-lg border-t border-line bg-surface-raised px-4 py-2">
      <slot name="footer" />
    </div>
  </div>
</template>
