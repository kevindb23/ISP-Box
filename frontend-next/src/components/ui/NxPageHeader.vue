<script setup lang="ts">
defineProps<{
  eyebrow: string;
  title: string;
  description: string;
}>();
</script>

<template>
  <header class="flex flex-col gap-5 rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] px-6 py-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
    <div>
      <p class="text-xs font-bold uppercase tracking-[0.12em] text-brand-600 dark:text-blue-400">{{ eyebrow }}</p>
      <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ title }}</h1>
      <p class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ description }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
