<script setup lang="ts">
import NxAlert from './NxAlert.vue';
import NxButton from './NxButton.vue';

withDefaults(defineProps<{ open: boolean; title: string; description: string; confirmLabel: string; busy?: boolean; danger?: boolean; error?: string }>(), { busy: false, danger: false, error: '' });
defineEmits<{ close: []; confirm: [] }>();
</script>

<template><Teleport to="body"><div v-if="open" class="nx-next-overlay fixed inset-0 z-[2000] grid place-items-center bg-slate-950/35 p-4" @click.self="$emit('close')"><section role="alertdialog" aria-modal="true" :aria-label="title" class="w-full max-w-md rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] p-6 text-[var(--nx-text)] shadow-2xl"><h2 class="text-lg font-bold">{{ title }}</h2><p class="mt-2 text-sm text-[var(--nx-text-muted)]">{{ description }}</p><NxAlert v-if="error" tone="danger" class="mt-4">{{ error }}</NxAlert><div class="mt-6 flex justify-end gap-2"><NxButton variant="secondary" @click="$emit('close')">Cancel</NxButton><NxButton :variant="danger ? 'danger' : 'primary'" :disabled="busy" @click="$emit('confirm')">{{ busy ? 'Working…' : confirmLabel }}</NxButton></div></section></div></Teleport></template>
