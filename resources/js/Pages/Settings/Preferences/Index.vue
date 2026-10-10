<template>
  <layout :layout-data="layoutData">
    <!-- breadcrumb -->
    <nav class="bg-white dark:bg-gray-900 sm:border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-8xl mx-auto hidden px-4 py-2 sm:px-6 md:block">
        <div class="flex items-baseline justify-between space-x-6">
          <ul class="text-sm">
            <li class="me-2 inline text-gray-600 dark:text-gray-400">
              {{ $t('You are here:') }}
            </li>
            <li class="me-2 inline">
              <InertiaLink :href="data.url.settings" class="text-accent hover:underline">
                {{ $t('Settings') }}
              </InertiaLink>
            </li>
            <li class="relative me-2 inline">
              <ChevronRight class="icon-breadcrumb relative inline h-3 w-3" />
            </li>
            <li class="inline">
              {{ $t('User preferences') }}
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="relative sm:mt-20">
      <div class="mx-auto max-w-3xl px-2 py-2 sm:px-6 sm:py-6 lg:px-8">
        <help-preference :data="data.help" />

        <theme :data="data.theme" />

        <locale :data="data.locale" />

        <name-order :data="data.name_order" />

        <date-format :data="data.date_format" />

        <number-format :data="data.number_format" />

        <distance-format :data="data.distance_format" />

        <timezone :data="data.timezone" />

        <maps :data="data.maps" />
      </div>
    </main>
  </layout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import NameOrder from '@/Pages/Settings/Preferences/Partials/NameOrder.vue';
import DateFormat from '@/Pages/Settings/Preferences/Partials/DateFormat.vue';
import NumberFormat from '@/Pages/Settings/Preferences/Partials/NumberFormat.vue';
import DistanceFormat from '@/Pages/Settings/Preferences/Partials/DistanceFormat.vue';
import Timezone from '@/Pages/Settings/Preferences/Partials/Timezone.vue';
import Maps from '@/Pages/Settings/Preferences/Partials/Maps.vue';
import Locale from '@/Pages/Settings/Preferences/Partials/Locale.vue';
import HelpPreference from '@/Pages/Settings/Preferences/Partials/HelpPreference.vue';
import Theme from '@/Pages/Settings/Preferences/Partials/Theme.vue';
import { ChevronRight } from 'lucide-vue-next';

export default {
  components: {
    ChevronRight,
    InertiaLink: Link,
    Layout,
    Theme,
    NameOrder,
    DateFormat,
    Timezone,
    NumberFormat,
    DistanceFormat,
    Maps,
    Locale,
    HelpPreference,
  },

  props: {
    layoutData: {
      type: Object,
      default: null,
    },
    data: {
      type: Object,
      default: null,
    },
  },
};
</script>
