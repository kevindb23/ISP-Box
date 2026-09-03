<script setup lang="ts">
withDefaults(defineProps<{
  tone?: 'neutral' | 'success' | 'info' | 'warning' | 'danger';
}>(), { tone: 'neutral' });

const tones = {
  neutral: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
  success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
  info: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
};
</script>

<template>
  <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="tones[tone]">
    <slot />
  </span>
</template>
