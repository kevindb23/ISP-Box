<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean;
  label: string;
  disabled?: boolean;
}>(), { disabled: false });

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

function toggle() {
  emit('update:modelValue', !props.modelValue);
}
</script>

<template>
  <div class="inline-flex items-center">
    <button
      type="button"
      role="switch"
      :aria-label="label"
      :aria-checked="modelValue"
      :disabled="disabled"
      :style="{ borderRadius: '9999px' }"
      class="nx-switch focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--nx-primary)]/25 disabled:cursor-not-allowed disabled:opacity-60"
      :class="{ 'is-on': modelValue }"
      @click.prevent.stop="toggle"
    >
      <span class="sr-only">{{ label }}</span>
      <span aria-hidden="true" class="nx-switch__state" :class="modelValue ? 'nx-switch__state--on' : 'nx-switch__state--off'">
        {{ modelValue ? 'ON' : 'OFF' }}
      </span>
      <span
        aria-hidden="true"
        class="nx-switch__thumb"
      />
    </button>
  </div>
</template>

<style scoped>
.nx-switch {
  position: relative;
  display: inline-flex !important;
  width: 88px !important;
  height: 36px !important;
  min-width: 88px !important;
  min-height: 36px !important;
  align-items: center !important;
  padding: 3px !important;
  overflow: hidden !important;
  appearance: none !important;
  box-sizing: border-box !important;
  border: 1px solid var(--nx-border) !important;
  border-radius: 9999px !important;
  clip-path: inset(0 round 9999px) !important;
  background: color-mix(in srgb, var(--nx-border) 72%, var(--nx-surface)) !important;
  transition: background-color 180ms ease, border-color 180ms ease;
}

.nx-switch.is-on {
  border-color: #45d99a !important;
  background: #45d99a !important;
}

.nx-switch__state {
  position: absolute;
  top: 0;
  display: flex;
  height: 100%;
  align-items: center;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1;
  pointer-events: none;
}

.nx-switch__state--on {
  left: 12px;
  color: #12392b !important;
}

.nx-switch__state--off {
  right: 11px;
  color: var(--nx-text-muted) !important;
}

.nx-switch__thumb {
  position: relative;
  z-index: 1;
  display: block;
  width: 28px !important;
  height: 28px !important;
  flex: 0 0 28px !important;
  border-radius: 50% !important;
  background: #fff !important;
  box-shadow: 0 2px 5px rgb(15 23 42 / 22%), 0 0 0 1px rgb(15 23 42 / 10%);
  transform: translateX(0);
  transition: transform 180ms ease;
}

.nx-switch.is-on .nx-switch__thumb {
  transform: translateX(50px) !important;
}
</style>
