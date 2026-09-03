<script setup lang="ts">
withDefaults(defineProps<{ minWidth?: string }>(), { minWidth: '980px' });
</script>

<template>
  <section class="min-w-0 max-w-full overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-left text-sm" :style="{ minWidth }">
        <slot />
      </table>
    </div>
    <slot name="footer" />
  </section>
</template>
