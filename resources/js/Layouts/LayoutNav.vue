<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
  links: Object,
});

const navigateToSelected = (event) => {
  router.visit(event.target.value);
};
</script>

<template>
  <div>
    <!-- vault sub menu on desktop -->
    <nav class="hidden sm:block bg-surface dark:border-slate-300/10 dark:bg-gray-900 sm:border-b sm:border-line-strong">
      <div class="max-w-8xl mx-auto px-4 py-2 sm:px-6 block">
        <ul class="list-none text-sm font-medium">
          <li v-for="link in links" :key="link.url" class="inline">
            <Link
              :href="link.url"
              :class="
                link.selected
                  ? 'bg-accent text-accent-contrast'
                  : 'text-ink hover:bg-surface-sunken dark:bg-accent-soft dark:text-slate-300 dark:hover:text-white'
              "
              class="me-2 rounded-md px-2 py-1 transition-colors">
              {{ link.title }}
            </Link>
          </li>
        </ul>
      </div>
    </nav>

    <!-- vault sub menu on mobile -->
    <nav class="block md:hidden px-4 py-2">
      <div class="relative">
        <select
          @change="navigateToSelected"
          class="w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-accent focus:outline-hidden focus:ring-accent dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
          <option value="" disabled>{{ $t('Select a page') }}</option>
          <option v-for="link in links" :key="link.url" :value="link.url" :selected="link.selected">
            {{ link.title }}
          </option>
        </select>
      </div>
    </nav>
  </div>
</template>
