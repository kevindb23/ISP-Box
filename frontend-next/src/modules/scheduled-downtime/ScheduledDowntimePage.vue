<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxBadge from '../../components/ui/NxBadge.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxModal from '../../components/ui/NxModal.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import NxTableShell from '../../components/ui/NxTableShell.vue';
import { getJson, postForm } from '../../lib/api';

type Downtime = {
  id: number;
  title: string;
  message: string;
  starts_at: string;
  ends_at: string;
  enabled: number;
  created_at?: string;
  updated_at?: string;
};

type FormState = Omit<Downtime, 'id' | 'created_at' | 'updated_at'>;

const emptyForm = (): FormState => ({ title: '', message: '', starts_at: '', ends_at: '', enabled: 1 });
const items = ref<Downtime[]>([]);
const form = ref<FormState>(emptyForm());
const editing = ref<Downtime | null>(null);
const modalOpen = ref(false);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const validation = ref<Record<string, string>>({});
const inputClass = 'min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)] outline-none transition focus:border-[var(--nx-primary)]';
const modalTitle = computed(() => editing.value ? 'Edit scheduled downtime' : 'New scheduled downtime');
const modalDescription = computed(() => editing.value ? 'Update the customer-facing maintenance window.' : 'Create a maintenance window that can be shown to subscribers later.');

function resetMessages(): void { error.value = ''; notice.value = ''; validation.value = {}; }

async function load(showNotice = false): Promise<void> {
  loading.value = true;
  resetMessages();
  try {
    const data = await getJson<{ items?: Downtime[] } | Downtime[]>('/api/v1/scheduled-downtime');
    items.value = Array.isArray(data) ? data : (data.items ?? []);
    if (showNotice) notice.value = 'Scheduled downtime refreshed.';
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unable to load scheduled downtime.';
  } finally { loading.value = false; }
}

function openCreate(): void { resetMessages(); editing.value = null; form.value = emptyForm(); modalOpen.value = true; }

function toLocalInput(value: string): string { return value ? value.replace(' ', 'T').slice(0, 16) : ''; }

function openEdit(item: Downtime): void {
  resetMessages();
  editing.value = item;
  form.value = { title: item.title, message: item.message, starts_at: toLocalInput(item.starts_at), ends_at: toLocalInput(item.ends_at), enabled: item.enabled ? 1 : 0 };
  modalOpen.value = true;
}

function closeModal(): void { if (!saving.value) modalOpen.value = false; }

function validLocalTimestamp(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return false;
  return !Number.isNaN(new Date(value).getTime());
}

function validate(): boolean {
  const next: Record<string, string> = {};
  if (!form.value.title.trim()) next.title = 'Title is required.';
  if (!form.value.message.trim()) next.message = 'Message is required.';
  if (!validLocalTimestamp(form.value.starts_at)) next.starts_at = 'Start time must be a valid timestamp.';
  if (!validLocalTimestamp(form.value.ends_at)) next.ends_at = 'End time must be a valid timestamp.';
  if (!next.starts_at && !next.ends_at && new Date(form.value.ends_at) <= new Date(form.value.starts_at)) next.ends_at = 'End time must be after start time.';
  validation.value = next;
  return Object.keys(next).length === 0;
}

async function save(): Promise<void> {
  resetMessages();
  if (!validate()) return;
  saving.value = true;
  try {
    const payload = new FormData();
    payload.set('title', form.value.title.trim());
    payload.set('message', form.value.message.trim());
    payload.set('starts_at', form.value.starts_at);
    payload.set('ends_at', form.value.ends_at);
    payload.set('enabled', form.value.enabled ? '1' : '0');
    const url = editing.value ? `/api/v1/scheduled-downtime/${editing.value.id}` : '/api/v1/scheduled-downtime';
    await postForm<Downtime>(url, payload);
    const message = editing.value ? 'Scheduled downtime updated.' : 'Scheduled downtime created.';
    modalOpen.value = false;
    await load();
    notice.value = message;
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unable to save scheduled downtime.';
  } finally { saving.value = false; }
}

async function toggle(item: Downtime): Promise<void> {
  resetMessages();
  try {
    const payload = new FormData();
    payload.set('enabled', item.enabled ? '0' : '1');
    await postForm<Downtime>(`/api/v1/scheduled-downtime/${item.id}/toggle`, payload);
    notice.value = `${item.title} is now ${item.enabled ? 'disabled' : 'enabled'}.`;
    await load();
  } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to update downtime status.'; }
}

async function remove(item: Downtime): Promise<void> {
  if (!window.confirm(`Delete “${item.title}”?`)) return;
  resetMessages();
  try {
    await postForm<unknown>(`/api/v1/scheduled-downtime/${item.id}/delete`, new FormData());
    notice.value = 'Scheduled downtime deleted.';
    await load();
  } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to delete scheduled downtime.'; }
}

function formatDate(value: string): string {
  if (!value) return '—';
  const date = new Date(value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

onMounted(() => void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Maintenance" title="Scheduled Downtime" description="Plan customer-facing maintenance windows and keep their status under administrator control.">
      <template #actions><NxButton variant="secondary" :disabled="loading || saving" @click="load(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton><NxButton aria-label="New scheduled downtime" :disabled="saving" @click="openCreate">New downtime</NxButton></template>
    </NxPageHeader>

    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>

    <NxCard title="Maintenance windows" description="These records are available for future subscriber portal maintenance notices.">
      <NxTableShell min-width="980px">
        <thead class="bg-[var(--nx-surface-muted)] text-xs uppercase"><tr><th class="px-4 py-3">Window</th><th class="px-4 py-3">Schedule</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
        <tbody>
          <tr v-if="loading && !items.length"><td colspan="4" class="p-12 text-center text-[var(--nx-text-muted)]">Loading scheduled downtime…</td></tr>
          <tr v-else-if="!items.length"><td colspan="4" class="p-12 text-center text-[var(--nx-text-muted)]">No scheduled downtime windows yet.</td></tr>
          <tr v-for="item in items" :key="item.id" class="border-t border-[var(--nx-border)]"><td class="px-4 py-3"><p class="font-semibold">{{ item.title }}</p><p class="mt-1 max-w-xl text-sm text-[var(--nx-text-muted)]">{{ item.message }}</p></td><td class="whitespace-nowrap px-4 py-3 text-sm"><p>{{ formatDate(item.starts_at) }}</p><p class="mt-1 text-[var(--nx-text-muted)]">to {{ formatDate(item.ends_at) }}</p></td><td class="px-4 py-3"><NxBadge :tone="item.enabled ? 'success' : 'neutral'">{{ item.enabled ? 'ENABLED' : 'DISABLED' }}</NxBadge></td><td class="px-4 py-3"><div class="flex justify-end gap-2"><NxButton variant="secondary" @click="openEdit(item)">Edit</NxButton><NxButton variant="secondary" @click="toggle(item)">{{ item.enabled ? 'Disable' : 'Enable' }}</NxButton><NxButton variant="danger" @click="remove(item)">Delete</NxButton></div></td></tr>
        </tbody>
      </NxTableShell>
    </NxCard>

    <NxModal :open="modalOpen" :title="modalTitle" :description="modalDescription" @close="closeModal">
      <form class="grid gap-4 px-6 py-5" @submit.prevent="save">
        <label class="grid gap-1.5 text-sm font-semibold">Title<input v-model="form.title" :class="inputClass" maxlength="160" autocomplete="off"><small v-if="validation.title" class="font-normal text-red-600">{{ validation.title }}</small></label>
        <label class="grid gap-1.5 text-sm font-semibold">Customer message<textarea v-model="form.message" :class="`${inputClass} py-3`" rows="4"></textarea><small v-if="validation.message" class="font-normal text-red-600">{{ validation.message }}</small></label>
        <div class="grid gap-4 md:grid-cols-2"><label class="grid gap-1.5 text-sm font-semibold">Starts at<input v-model="form.starts_at" :class="inputClass" type="datetime-local"><small v-if="validation.starts_at" class="font-normal text-red-600">{{ validation.starts_at }}</small></label><label class="grid gap-1.5 text-sm font-semibold">Ends at<input v-model="form.ends_at" :class="inputClass" type="datetime-local"><small v-if="validation.ends_at" class="font-normal text-red-600">{{ validation.ends_at }}</small></label></div>
        <label class="flex items-center gap-3 text-sm font-semibold"><input v-model="form.enabled" type="checkbox" class="size-4 accent-[var(--nx-primary)]"> Enable this window</label>
        <button class="sr-only" type="submit">Save</button>
      </form>
      <template #footer><NxButton variant="secondary" :disabled="saving" @click="closeModal">Cancel</NxButton><NxButton :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save downtime' }}</NxButton></template>
    </NxModal>
  </div>
</template>

<style scoped>
:global([data-nx-next-root="scheduled-downtime"]) { max-width: 100%; overflow-x: hidden; }
</style>
