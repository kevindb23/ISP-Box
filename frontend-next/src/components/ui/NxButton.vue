<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset';
  variant?: 'primary' | 'secondary' | 'danger';
  disabled?: boolean;
}>(), { type: 'button', variant: 'primary', disabled: false });

const variants = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:ring-brand-500/30',
  secondary: 'border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] text-[var(--nx-text)] hover:bg-slate-100 dark:hover:bg-slate-700',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500/30',
};
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex min-h-10 items-center justify-center rounded-md px-4 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-50"
    :class="variants[variant]"
  >
    <slot />
  </button>
</template>
