<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'success' | 'danger' | 'warning' | 'info' }>(), { tone: 'info' });
const tones = {
  success: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
  danger: 'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
  warning: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
  info: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200',
};
</script>

<template><div role="alert" class="rounded-md border px-4 py-3 text-sm" :class="tones[tone]"><slot /></div></template>
