<script setup lang="ts">
withDefaults(defineProps<{ open: boolean; title: string; description?: string; maxWidth?: string }>(), { description: '', maxWidth: '48rem' });
defineEmits<{ close: [] }>();
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="nx-next-overlay fixed inset-0 z-[2000] grid place-items-center overflow-y-auto bg-slate-950/35 p-4" @click.self="$emit('close')">
      <section role="dialog" aria-modal="true" :aria-label="title" class="my-6 w-full overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] text-[var(--nx-text)] shadow-2xl" :style="{ maxWidth }">
        <header class="flex items-start justify-between border-b border-[var(--nx-border)] px-6 py-4"><div><h2 class="text-lg font-bold">{{ title }}</h2><p v-if="description" class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ description }}</p></div><button type="button" aria-label="Close" class="rounded p-2 hover:bg-[var(--nx-surface-muted)]" @click="$emit('close')">✕</button></header>
        <slot />
        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-[var(--nx-border)] px-6 py-4"><slot name="footer" /></footer>
      </section>
    </div>
  </Teleport>
</template>
