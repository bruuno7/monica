<script setup>
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Flame, Trophy } from 'lucide-vue-next';

/**
 * A year of writing at a glance: one square per day, darker when more happened
 * (notes, calls, posts, life events, mood check-ins), plus the current streak.
 */
const props = defineProps({
  data: Object,
});

// Lay the days out in columns of seven (one column per week), starting on Monday.
const columns = computed(() => {
  const leading = (props.data.start_weekday ?? 1) - 1;
  const cells = [...Array(leading).fill(null), ...props.data.days];
  const result = [];
  for (let i = 0; i < cells.length; i += 7) {
    result.push(cells.slice(i, i + 7));
  }
  return result;
});

const max = computed(() => Math.max(1, ...props.data.days.map((day) => day.count)));

const level = (day) => {
  if (!day || day.count === 0) return 'bg-surface-sunken';
  const ratio = day.count / max.value;
  if (ratio <= 0.25) return 'bg-accent opacity-30';
  if (ratio <= 0.5) return 'bg-accent opacity-55';
  if (ratio <= 0.75) return 'bg-accent opacity-80';
  return 'bg-accent';
};

const title = (day) =>
  day
    ? day.count === 0
      ? trans('Nothing on :date', { date: day.date })
      : trans(':count entry on :date|:count entries on :date', day.count, { count: day.count, date: day.date })
    : '';

// month labels along the top: the first column that contains the first day of a month
const months = computed(() =>
  columns.value
    .map((column, index) => {
      const first = column.find((day) => day && day.date.endsWith('-01'));
      if (!first) return null;
      const date = new Date(`${first.date}T00:00:00`);
      return { index, label: date.toLocaleDateString(undefined, { month: 'short' }) };
    })
    .filter(Boolean),
);
</script>

<template>
  <div class="rounded-lg border border-line bg-surface-card p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h3 class="font-semibold text-ink">{{ $t('Your year of writing') }}</h3>
      <div class="flex items-center gap-4 text-sm">
        <span class="flex items-center gap-1.5 text-ink" :title="$t('Current streak')">
          <Flame class="h-4 w-4 text-orange-500" />
          <span class="tabular font-medium">{{
            $tChoice(':count day|:count days', data.streak.current, { count: data.streak.current })
          }}</span>
        </span>
        <span class="flex items-center gap-1.5 text-ink-muted" :title="$t('Best streak')">
          <Trophy class="h-4 w-4" />
          <span class="tabular">{{
            $tChoice(':count day|:count days', data.streak.best, { count: data.streak.best })
          }}</span>
        </span>
      </div>
    </div>

    <div class="overflow-x-auto pb-1">
      <div class="relative inline-block">
        <div class="relative mb-1 h-4 text-[10px] text-ink-subtle">
          <span v-for="month in months" :key="month.index" class="absolute" :style="{ left: `${month.index * 13}px` }">
            {{ month.label }}
          </span>
        </div>
        <div class="flex gap-[3px]">
          <div v-for="(column, c) in columns" :key="c" class="flex flex-col gap-[3px]">
            <span
              v-for="(day, d) in column"
              :key="d"
              class="h-[10px] w-[10px] rounded-[2px]"
              :class="day ? level(day) : 'opacity-0'"
              :title="title(day)"></span>
          </div>
        </div>
      </div>
    </div>

    <p class="mt-3 text-xs text-ink-muted">
      {{
        $tChoice(':count entry over the last year|:count entries over the last year', data.total, { count: data.total })
      }}
      ·
      {{ $tChoice('active on :count day|active on :count days', data.active_days, { count: data.active_days }) }}
    </p>
  </div>
</template>
