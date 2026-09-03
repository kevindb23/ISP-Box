<script setup lang="ts">
withDefaults(defineProps<{ label: string; span?: 'one' | 'two' }>(), { span: 'one' });
</script>

<template><label class="grid gap-1.5 text-sm font-semibold" :class="span === 'two' ? 'md:col-span-2' : ''"><span>{{ label }}</span><slot /></label></template>
