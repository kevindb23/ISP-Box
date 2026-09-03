<script setup lang="ts">
import NxButton from './NxButton.vue';

defineProps<{
  page: number;
  totalPages: number;
  totalRecords: number;
}>();

defineEmits<{
  previous: [];
  next: [];
}>();
</script>

<template>
  <footer class="flex flex-col gap-3 border-t border-[var(--nx-border)] px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
    <span class="text-[var(--nx-text-muted)]">Page {{ page }} of {{ totalPages }} · {{ totalRecords }} records</span>
    <div class="flex gap-2">
      <NxButton variant="secondary" :disabled="page <= 1" @click="$emit('previous')">Previous</NxButton>
      <NxButton variant="secondary" :disabled="page >= totalPages" @click="$emit('next')">Next</NxButton>
    </div>
  </footer>
</template>
