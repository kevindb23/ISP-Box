<script setup lang="ts">
import NxButton from '../components/ui/NxButton.vue';
import NxCard from '../components/ui/NxCard.vue';
</script>

<template>
  <section class="grid gap-4" aria-label="NexusBox next-generation UI preview">
    <NxCard title="NexusBox UI foundation" description="Isolated Vue, TypeScript, and Tailwind component layer.">
      <div class="flex flex-wrap gap-2">
        <NxButton>Primary action</NxButton>
        <NxButton variant="secondary">Secondary action</NxButton>
        <NxButton variant="danger">Danger action</NxButton>
      </div>
    </NxCard>
  </section>
</template>
