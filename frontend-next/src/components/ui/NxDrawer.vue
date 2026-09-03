<script setup lang="ts">
withDefaults(defineProps<{ open: boolean; eyebrow?: string; title: string; subtitle?: string; maxWidth?: string }>(), { eyebrow: '', subtitle: '', maxWidth: '40rem' });
defineEmits<{ close: [] }>();
</script>

<template>
  <Teleport to="body"><div v-if="open" class="nx-next-overlay fixed inset-0 z-[2000] flex justify-end bg-slate-950/30" @click.self="$emit('close')"><aside role="dialog" aria-modal="true" :aria-label="title" class="h-full w-full overflow-y-auto border-l border-[var(--nx-border)] bg-[var(--nx-surface)] p-6 text-[var(--nx-text)] shadow-2xl" :style="{ maxWidth }"><header class="flex items-start justify-between"><div><p v-if="eyebrow" class="text-xs font-bold uppercase tracking-wide text-brand-600">{{ eyebrow }}</p><h2 class="mt-1 text-xl font-bold">{{ title }}</h2><p v-if="subtitle" class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ subtitle }}</p></div><button type="button" aria-label="Close" class="rounded p-2 hover:bg-[var(--nx-surface-muted)]" @click="$emit('close')">✕</button></header><div class="mt-6"><slot /></div><div v-if="$slots.actions" class="mt-6 flex flex-wrap gap-2"><slot name="actions" /></div></aside></div></Teleport>
</template>
