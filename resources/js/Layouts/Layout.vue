<script setup>
import { onMounted } from 'vue';
import { Link, Head, router } from '@inertiajs/vue3';
import Toaster from '@/Shared/Toaster.vue';
import FooterLayout from '@/Layouts/FooterLayout.vue';
import LayoutNav from '@/Layouts/LayoutNav.vue';
import ThemeSwitcher from '@/Shared/ThemeSwitcher.vue';
import { Settings, LogOut, ScanSearch, ChevronRight } from 'lucide-vue-next';
import { flash, watchSystemTheme } from '@/methods.js';

const props = defineProps({
  title: String,
  insideVault: Boolean,
  layoutData: Object,
});

onMounted(() => {
  watchSystemTheme();

  if (localStorage.success) {
    flash(localStorage.success, 'success');
    localStorage.removeItem('success');
  }
});

const goToSearchPage = () => {
  router.visit(props.layoutData.vault.url.search, {
    data: { searchTerm: '' },
  });
};
</script>

<template>
  <Head :title="title" />

  <div class="min-h-full">
    <div class="sm:fixed top-0 z-10 w-full">
      <!-- main nav - only displayed on desktop -->
      <nav
        class="hidden max-w-8xl mx-auto sm:flex h-10 items-center justify-between border-b border-line-strong bg-surface-raised px-3 text-ink sm:px-6">
        <div
          class="dark:highlight-white/5 items-center rounded-lg border border-line bg-surface px-2 py-1 text-sm dark:border-0 dark:bg-gray-900 sm:flex">
          <Link :href="layoutData.url.vaults" class="shrink-0 text-accent hover:underline">
            {{ layoutData.user.name }}
          </Link>

          <!-- information about the current vault -->
          <div v-if="layoutData.vault" class="flex items-center">
            <ChevronRight class="mx-1 h-3 w-3 text-ink-subtle" />
            {{ layoutData.vault.name }}
          </div>
        </div>

        <!-- search box -->
        <div v-if="insideVault" class="flew-grow relative">
          <ScanSearch class="absolute start-2 top-2 h-4 w-4 text-ink-subtle" />
          <input
            type="text"
            class="dark:highlight-white/5 block w-64 rounded-md border border-line-strong px-2 py-1 text-center placeholder:text-ink-muted hover:cursor-pointer focus:border-accent focus:ring-accent dark:border-0 dark:bg-slate-900 dark:hover:bg-slate-700 sm:text-sm"
            :placeholder="$t('Search something')"
            @focus="goToSearchPage" />
        </div>

        <!-- icons -->
        <div class="flex items-center justify-end gap-4">
          <ThemeSwitcher />

          <Link :href="layoutData.url.settings" class="relative flex items-center gap-1 group">
            <Settings class="h-4 w-4 text-ink-muted transition-colors group-hover:text-ink" />
            <span class="text-sm text-accent group-hover:underline">{{ $t('Settings') }}</span>
          </Link>
          <Link
            class="relative flex items-center gap-1 cursor-pointer group"
            method="post"
            :href="route('logout')"
            as="button">
            <LogOut class="h-4 w-4 text-ink-muted transition-colors group-hover:text-ink" />
            <span class="text-sm text-accent group-hover:underline">{{ $t('Logout') }}</span>
          </Link>
        </div>
      </nav>

      <!-- mobile nav -->
      <div class="sm:hidden pt-3 border-b border-line-strong bg-surface-raised px-3 text-ink sm:px-6">
        <!-- user / vault & logout -->
        <div class="flex mb-2 items-center justify-between text-sm">
          <div class="flex items-center rounded-lg border border-line bg-surface px-2 py-1 dark:bg-gray-900">
            <Link :href="layoutData.url.vaults" class="shrink-0 text-accent">
              {{ layoutData.user.name }}
            </Link>

            <!-- information about the current vault -->
            <div v-if="layoutData.vault" class="flex items-center">
              <ChevronRight class="mx-1 h-3 w-3 text-ink-subtle" />
              {{ layoutData.vault.name }}
            </div>
          </div>

          <div class="flex items-center gap-2">
            <ThemeSwitcher :compact="true" />

            <!-- settings -->
            <Link
              :href="layoutData.url.settings"
              :aria-label="$t('Settings')"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-surface dark:bg-gray-900">
              <Settings class="h-4 w-4" />
            </Link>

            <!-- logout -->
            <Link
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-surface dark:bg-gray-900"
              method="post"
              :href="route('logout')"
              :aria-label="$t('Logout')"
              as="button">
              <LogOut class="h-4 w-4" />
            </Link>
          </div>
        </div>

        <!-- search box -->
        <div v-if="insideVault" class="relative mb-2">
          <ScanSearch class="absolute start-2 top-2 h-4 w-4 text-ink-subtle" />
          <input
            type="text"
            class="block w-full rounded-md border border-line-strong bg-surface px-2 py-1 text-center placeholder:text-ink-muted hover:cursor-pointer focus:border-accent focus:ring-accent dark:border-0 dark:bg-slate-900 dark:hover:bg-slate-700 sm:text-sm"
            :placeholder="$t('Search something')"
            @focus="goToSearchPage" />
        </div>
      </div>

      <LayoutNav
        v-if="insideVault"
        :links="
          [
            {
              url: layoutData.vault.url.dashboard,
              title: $t('Dashboard'),
              selected: $page.component === 'Vault/Dashboard/Index',
            },
            {
              url: layoutData.vault.url.contacts,
              title: $t('Contacts'),
              selected: $page.component.startsWith('Vault/Contact'),
            },
            layoutData.vault.visibility.show_calendar_tab
              ? {
                  url: layoutData.vault.url.calendar,
                  title: $t('Calendar'),
                  selected: $page.component.startsWith('Vault/Calendar'),
                }
              : null,
            layoutData.vault.visibility.show_journal_tab
              ? {
                  url: layoutData.vault.url.journals,
                  title: $t('Journals'),
                  selected: $page.component.startsWith('Vault/Journal'),
                }
              : null,
            layoutData.vault.visibility.show_group_tab
              ? {
                  url: layoutData.vault.url.groups,
                  title: $t('Groups'),
                  selected: $page.component.startsWith('Vault/Group'),
                }
              : null,
            layoutData.vault.visibility.show_companies_tab
              ? {
                  url: layoutData.vault.url.companies,
                  title: $t('Companies'),
                  selected: $page.component.startsWith('Vault/Companies'),
                }
              : null,
            layoutData.vault.visibility.show_tasks_tab
              ? {
                  url: layoutData.vault.url.tasks,
                  title: $t('Tasks'),
                  selected: $page.component.startsWith('Vault/Dashboard/Task'),
                }
              : null,
            layoutData.vault.visibility.show_reports_tab
              ? {
                  url: layoutData.vault.url.reports,
                  title: $t('Reports'),
                  selected: $page.component.startsWith('Vault/Reports'),
                }
              : null,
            layoutData.vault.visibility.show_files_tab
              ? {
                  url: layoutData.vault.url.files,
                  title: $t('Files'),
                  selected: $page.component.startsWith('Vault/Files'),
                }
              : null,
            layoutData.vault.permission.at_least_editor
              ? {
                  url: layoutData.vault.url.settings,
                  title: $t('Vault settings'),
                  selected: $page.component.startsWith('Vault/Settings'),
                }
              : null,
          ].filter((f) => f !== null)
        " />
    </div>

    <!-- Page Heading -->
    <header
      v-if="$slots.header"
      class="relative mb-8 mt-10 bg-white dark:bg-gray-900 sm:border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-8xl mx-auto hidden px-4 py-2 sm:px-6 md:block">
        <slot name="header" />
      </div>
    </header>

    <main class="relative mb-8 mt-10">
      <slot />
    </main>

    <FooterLayout />
  </div>

  <Toaster />
</template>
