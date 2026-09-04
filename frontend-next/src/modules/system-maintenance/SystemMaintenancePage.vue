<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxBadge from '../../components/ui/NxBadge.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxModal from '../../components/ui/NxModal.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import { getJson, postForm } from '../../lib/api';

type MaintenanceSettings = {
  id: number;
  enabled: boolean;
  message: string;
  starts_at: string | null;
  ends_at: string | null;
  active: boolean;
};

const defaults = (): MaintenanceSettings => ({
  id: 1,
  enabled: false,
  message: '',
  starts_at: null,
  ends_at: null,
  active: false,
});

const config = ref<MaintenanceSettings>(defaults());
const original = ref<MaintenanceSettings>(defaults());
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const previewOpen = ref(false);
const inputClass = 'min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)] outline-none transition focus:border-[var(--nx-primary)]';

const scheduled = computed(() => Boolean(config.value.starts_at && config.value.ends_at));
const dirty = computed(() => JSON.stringify(config.value) !== JSON.stringify(original.value));
const expired = computed(() => {
  if (!scheduled.value || !config.value.ends_at) return false;
  const end = new Date(config.value.ends_at).getTime();
  return Number.isFinite(end) && end < Date.now();
});
const statusLabel = computed(() => {
  if (config.value.active) return 'Active now';
  if (config.value.enabled && scheduled.value && !expired.value) return 'Scheduled';
  return 'Inactive';
});
const statusTone = computed<'success' | 'warning' | 'neutral'>(() => {
  if (config.value.active) return 'success';
  if (config.value.enabled && scheduled.value) return 'warning';
  return 'neutral';
});

function applySettings(data: Partial<MaintenanceSettings>): void {
  config.value = {
    ...defaults(),
    ...data,
    enabled: Boolean(data.enabled),
    starts_at: data.starts_at || null,
    ends_at: data.ends_at || null,
    active: Boolean(data.active),
  };
  original.value = { ...config.value };
}

async function load(showNotice = false): Promise<void> {
  loading.value = true;
  error.value = '';
  notice.value = '';

  try {
    applySettings(await getJson<Partial<MaintenanceSettings>>('/api/v1/system-maintenance'));
    if (showNotice) notice.value = 'System maintenance settings refreshed.';
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Unable to load system maintenance settings.';
  } finally {
    loading.value = false;
  }
}

function discard(): void {
  config.value = { ...original.value };
  notice.value = 'Unsaved changes discarded.';
  error.value = '';
}

async function save(): Promise<void> {
  saving.value = true;
  error.value = '';
  notice.value = '';

  try {
    const form = new FormData();
    form.set('enabled', config.value.enabled ? '1' : '0');
    form.set('message', config.value.message);
    form.set('starts_at', config.value.starts_at || '');
    form.set('ends_at', config.value.ends_at || '');

    const response = await postForm<MaintenanceSettings>('/api/v1/system-maintenance', form);
    applySettings(response.data);
    notice.value = response.message || 'System maintenance settings saved.';
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Unable to save system maintenance settings.';
  } finally {
    saving.value = false;
  }
}

onMounted(() => void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader
      eyebrow="Maintenance"
      title="System Maintenance"
      description="Temporarily show a customer-facing maintenance message while keeping subscriber login available."
    >
      <template #actions>
        <NxButton variant="secondary" :disabled="loading || saving" @click="load(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton>
        <NxButton :disabled="loading || saving || !dirty" @click="save">{{ saving ? 'Saving…' : 'Save Settings' }}</NxButton>
      </template>
    </NxPageHeader>

    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>

    <NxCard>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-base font-bold">Maintenance state</p>
          <p class="mt-1 text-sm text-[var(--nx-text-muted)]">Subscribers can still authenticate; active maintenance replaces portal content with your message.</p>
        </div>
        <NxBadge :tone="statusTone" data-testid="maintenance-status">{{ statusLabel }}</NxBadge>
      </div>

      <div class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4">
        <div>
          <p class="font-bold">Enable maintenance mode</p>
          <p class="mt-1 text-sm text-[var(--nx-text-muted)]">Leave the schedule empty to activate immediately after saving.</p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="config.enabled"
          class="relative h-7 w-12 rounded-full bg-[var(--nx-border)] transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30"
          :class="config.enabled ? 'bg-emerald-500' : ''"
          @click="config.enabled = !config.enabled"
        >
          <span class="absolute top-1 size-5 rounded-full bg-white shadow transition-all" :class="config.enabled ? 'left-6' : 'left-1'"></span>
          <span class="sr-only">{{ config.enabled ? 'Disable' : 'Enable' }} maintenance mode</span>
        </button>
      </div>
    </NxCard>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.8fr)]">
      <NxCard title="Maintenance message" description="This message is shown to subscribers while maintenance is active.">
        <label class="grid gap-1.5 text-sm font-semibold">
          Customer-facing message
          <textarea v-model="config.message" rows="6" maxlength="1000" required :class="inputClass + ' py-3'"></textarea>
          <span class="font-normal text-[var(--nx-text-muted)]">Explain what customers should expect and when to try again.</span>
        </label>
      </NxCard>

      <NxCard title="Schedule" description="Optional. Both values are required for scheduled mode.">
        <div class="grid gap-4">
          <label class="grid gap-1.5 text-sm font-semibold">
            Start date and time
            <input v-model="config.starts_at" type="datetime-local" :class="inputClass" :disabled="!config.enabled && !scheduled">
          </label>
          <label class="grid gap-1.5 text-sm font-semibold">
            End date and time
            <input v-model="config.ends_at" type="datetime-local" :class="inputClass" :disabled="!config.enabled && !scheduled">
          </label>
          <NxAlert v-if="config.enabled && scheduled" tone="info">The portal will show this message only between the start and end times.</NxAlert>
          <NxAlert v-else tone="warning">No schedule is set. Enabling this configuration activates maintenance immediately.</NxAlert>
        </div>
      </NxCard>
    </div>

    <NxCard>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-base font-bold">Subscriber preview</p>
          <p class="mt-1 text-sm text-[var(--nx-text-muted)]">Review the message before enabling maintenance.</p>
        </div>
        <NxButton variant="secondary" :disabled="!config.message.trim()" @click="previewOpen = true">Preview message</NxButton>
      </div>
    </NxCard>

    <NxModal v-if="previewOpen" :open="previewOpen" title="Subscriber maintenance preview" description="This is the customer-facing state shown after subscriber login." @close="previewOpen = false">
      <div class="p-6">
        <div class="mx-auto max-w-2xl rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-8 text-center">
          <div class="mx-auto grid size-14 place-items-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200">⚙</div>
          <p class="mt-4 text-xs font-bold uppercase tracking-[0.12em] text-brand-600">System maintenance</p>
          <h2 class="mt-2 text-xl font-bold">We are sorry for the interruption</h2>
          <p class="mt-3 whitespace-pre-line text-sm text-[var(--nx-text-muted)]">{{ config.message || 'Your maintenance message will appear here.' }}</p>
          <p class="mt-3 text-xs text-[var(--nx-text-muted)]">Thank you for your patience. Please check back shortly.</p>
        </div>
      </div>
      <template #footer><NxButton variant="secondary" @click="previewOpen = false">Close</NxButton></template>
    </NxModal>
  </div>
</template>

<style scoped>
:global([data-nx-next-root="system-maintenance"]) {
  max-width: 100%;
  overflow-x: hidden;
}
</style>
