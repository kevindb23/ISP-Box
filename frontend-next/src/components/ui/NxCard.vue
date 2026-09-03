<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>

<template>
  <article class="overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] text-[var(--nx-text)] shadow-sm">
    <header v-if="title || description" class="border-b border-[var(--nx-border)] px-6 py-4">
      <h2 v-if="title" class="text-base font-bold">{{ title }}</h2>
      <p v-if="description" class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ description }}</p>
    </header>
    <div class="p-6"><slot /></div>
  </article>
</template>
