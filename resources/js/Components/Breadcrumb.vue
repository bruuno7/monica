<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps({
  items: Array,
});
</script>

<template>
  <div class="flex items-baseline justify-between space-x-6">
    <ul class="text-sm">
      <li class="me-2 inline text-gray-600 dark:text-gray-400">
        {{ $t('You are here:') }}
      </li>
      <template v-for="(item, index) in items" :key="index">
        <li v-if="index > 0" class="relative ms-2 inline">
          <ChevronRight class="icon-breadcrumb relative inline h-3 w-3" />
        </li>

        <li class="inline" :class="{ 'ms-2': index > 0 }">
          <Link v-if="item.url" :href="item.url" class="text-accent hover:underline">
            {{ item.name }}
          </Link>
          <template v-else>
            {{ item.name }}
          </template>
        </li>
      </template>
    </ul>
  </div>
</template>
