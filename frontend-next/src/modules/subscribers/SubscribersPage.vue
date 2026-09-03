<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import NxBadge from '../../components/ui/NxBadge.vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxConfirmDialog from '../../components/ui/NxConfirmDialog.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import NxPagination from '../../components/ui/NxPagination.vue';
import NxTableShell from '../../components/ui/NxTableShell.vue';
import NxToolbar from '../../components/ui/NxToolbar.vue';
import {
  createSubscriber, deleteSubscriber, getSubscriber, listSubscriberPlans, listSubscribers,
  reactivateSubscriber, resetPortalPassword, resetPppPassword, suspendSubscriber, updateSubscriber,
} from './api';
import type { SubscriberCredentials, SubscriberInput, SubscriberPlanOption, SubscriberRow } from './contracts';

const props = defineProps<{ initialSubscribers: SubscriberRow[]; initialPlans: SubscriberPlanOption[] }>();
const rows = ref([...props.initialSubscribers]);
const plans = ref([...props.initialPlans]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const dialogError = ref('');
const search = ref('');
const statusFilter = ref('');
const connectionFilter = ref('');
const page = ref(1);
const perPage = 10;
const formOpen = ref(false);
const viewSubscriber = ref<SubscriberRow | null>(null);
const detailLoading = ref(false);
const credentials = ref<SubscriberCredentials | null>(null);
const pendingAction = ref<{ kind: 'suspend' | 'reactivate' | 'reset-ppp' | 'reset-portal' | 'delete'; subscriber: SubscriberRow } | null>(null);

const form = reactive<SubscriberInput & { id: number | null }>({ id: null, full_name: '', address: '', contact_number: '', email: '', plan_id: 0 });

const filtered = computed(() => {
  const query = search.value.trim().toLowerCase();
  return rows.value.filter((row) => {
    const haystack = [row.account_number, row.full_name, row.email, row.contact_number, row.ppp_username, row.plan_name, row.service_status, row.online_text].join(' ').toLowerCase();
    return (!query || haystack.includes(query))
      && (!statusFilter.value || row.service_status.toUpperCase() === statusFilter.value)
      && (!connectionFilter.value || (row.online ? 'ONLINE' : 'OFFLINE') === connectionFilter.value);
  });
});
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const pagedRows = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));
const stats = computed(() => ({
  total: rows.value.length,
  active: rows.value.filter((row) => row.service_status.toUpperCase() === 'ACTIVE').length,
  suspended: rows.value.filter((row) => row.service_status.toUpperCase() === 'SUSPENDED').length,
  online: rows.value.filter((row) => Number(row.online) === 1).length,
}));

watch([search, statusFilter, connectionFilter], () => { page.value = 1; });
watch(totalPages, (value) => { if (page.value > value) page.value = value; });
watch(pendingAction, () => { dialogError.value = ''; });

function resetMessages(): void { error.value = ''; notice.value = ''; dialogError.value = ''; }
function openCreate(): void {
  Object.assign(form, { id: null, full_name: '', address: '', contact_number: '', email: '', plan_id: 0 });
  resetMessages();
  formOpen.value = true;
}
async function openEdit(row: SubscriberRow): Promise<void> {
  resetMessages();
  Object.assign(form, {
    id: row.id,
    full_name: row.full_name || '',
    address: row.address || '',
    contact_number: row.contact_number || '',
    email: row.email || '',
    plan_id: Number(row.plan_id || 0),
  });
  formOpen.value = true;
}
async function openView(row: SubscriberRow): Promise<void> {
  detailLoading.value = true;
  resetMessages();
  try {
    const detail = await getSubscriber(row.id);
    viewSubscriber.value = {
      ...row,
      ...detail,
      email: String(detail.email || '').trim() || row.email,
      contact_number: String(detail.contact_number || '').trim() || row.contact_number,
      address: String(detail.address || '').trim() || row.address,
      plan_name: detail.plan_name || row.plan_name,
      ppp_username: detail.ppp_username || row.ppp_username,
    };
  }
  catch (reason) { error.value = reason instanceof Error ? reason.message : 'Failed to load subscriber.'; }
  finally { detailLoading.value = false; }
}
async function refresh(showNotice = false): Promise<void> {
  loading.value = true; resetMessages();
  try {
    const [nextRows, nextPlans] = await Promise.all([listSubscribers(), listSubscriberPlans()]);
    rows.value = nextRows; plans.value = nextPlans;
    if (showNotice) notice.value = 'Subscribers refreshed.';
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Failed to load subscribers.'; }
  finally { loading.value = false; }
}
async function submit(): Promise<void> {
  dialogError.value = '';
  if (!form.full_name.trim()) { dialogError.value = 'Full name is required.'; return; }
  if (!form.email.trim()) { dialogError.value = 'Email is required for subscriber portal login.'; return; }
  if (!form.plan_id) { dialogError.value = 'Plan is required.'; return; }
  saving.value = true;
  try {
    const input: SubscriberInput = { full_name: form.full_name.trim(), address: form.address.trim(), contact_number: form.contact_number.trim(), email: form.email.trim(), plan_id: Number(form.plan_id) };
    if (form.id) {
      const result = await updateSubscriber(form.id, input);
      formOpen.value = false; await refresh(false); notice.value = result.message || 'Subscriber updated.';
    } else {
      const result = await createSubscriber(input);
      formOpen.value = false; credentials.value = result.data || {}; await refresh(false);
    }
  } catch (reason) { dialogError.value = reason instanceof Error ? reason.message : 'Failed to save subscriber.'; }
  finally { saving.value = false; }
}
async function confirmAction(): Promise<void> {
  if (!pendingAction.value) return;
  const { kind, subscriber } = pendingAction.value;
  saving.value = true; dialogError.value = '';
  try {
    if (kind === 'suspend') notice.value = (await suspendSubscriber(subscriber.id)).message;
    if (kind === 'reactivate') notice.value = (await reactivateSubscriber(subscriber.id)).message;
    if (kind === 'reset-ppp') credentials.value = (await resetPppPassword(subscriber.id)).data || {};
    if (kind === 'reset-portal') credentials.value = (await resetPortalPassword(subscriber.id)).data || {};
    if (kind === 'delete') notice.value = (await deleteSubscriber(subscriber.id)).message;
    pendingAction.value = null;
    await refresh(false);
  } catch (reason) { dialogError.value = reason instanceof Error ? reason.message : 'Subscriber action failed.'; }
  finally { saving.value = false; }
}

const actionCopy = computed(() => {
  const kind = pendingAction.value?.kind;
  return {
    title: kind === 'suspend' ? 'Suspend subscriber?' : kind === 'reactivate' ? 'Reactivate subscriber?' : kind === 'reset-ppp' ? 'Reset PPP password?' : kind === 'reset-portal' ? 'Reset portal password?' : 'Delete subscriber?',
    text: kind === 'suspend' ? 'This terminates the PPP session and suspends portal service.' : kind === 'reactivate' ? 'This restores the subscriber service and portal access.' : kind === 'reset-ppp' ? 'This changes RADIUS credentials and pushes the new password to the linked ONT through ACS.' : kind === 'reset-portal' ? 'The existing subscriber portal password will stop working.' : 'This removes the subscriber and related authentication records. This cannot be undone.',
    confirm: kind === 'delete' ? 'Delete Subscriber' : kind?.startsWith('reset') ? 'Reset Password' : kind === 'suspend' ? 'Suspend' : 'Reactivate',
  };
});

function serviceTone(status: string): 'success' | 'warning' | 'danger' | 'neutral' {
  const value = status.toUpperCase();
  return value === 'ACTIVE' ? 'success' : value === 'SUSPENDED' ? 'warning' : value === 'TERMINATED' ? 'danger' : 'neutral';
}
</script>

<template>
  <div class="space-y-4 pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Subscriber registry" title="Subscribers" description="Manage subscriber accounts, plan assignments, credentials, and service state across the access network.">
      <template #actions><NxButton @click="openCreate">+ Add Subscriber</NxButton><NxButton variant="secondary" :disabled="loading" @click="refresh(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton></template>
    </NxPageHeader>

    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <NxCard v-for="item in [['Total Subscribers',stats.total,'Registered commercial accounts'],['Active Services',stats.active,'Commercially active lines'],['Suspended',stats.suspended,'Temporarily disabled services'],['Currently Online',stats.online,'Live PPP sessions in RADIUS']]" :key="String(item[0])"><p class="text-xs font-semibold uppercase tracking-wide text-[var(--nx-text-muted)]">{{ item[0] }}</p><p class="mt-2 text-2xl font-bold">{{ item[1] }}</p><p class="mt-1 text-xs text-[var(--nx-text-muted)]">{{ item[2] }}</p></NxCard>
    </section>

    <NxToolbar><div class="grid gap-3 lg:grid-cols-[minmax(280px,1fr)_180px_180px]"><label><span class="sr-only">Search subscribers</span><input v-model="search" type="search" placeholder="Search account, subscriber, email, PPP username or plan…" class="min-h-11 w-full rounded-md bg-[var(--nx-surface-muted)] px-4 text-sm text-[var(--nx-text)] outline-none"></label><select v-model="statusFilter" aria-label="Service status" class="min-h-11 rounded-md bg-[var(--nx-surface-muted)] px-3 text-sm text-[var(--nx-text)]"><option value="">All Statuses</option><option value="ACTIVE">Active</option><option value="SUSPENDED">Suspended</option><option value="PENDING">Pending</option><option value="TERMINATED">Terminated</option></select><select v-model="connectionFilter" aria-label="Connection status" class="min-h-11 rounded-md bg-[var(--nx-surface-muted)] px-3 text-sm text-[var(--nx-text)]"><option value="">All Connections</option><option value="ONLINE">Online</option><option value="OFFLINE">Offline</option></select></div></NxToolbar>

    <NxTableShell min-width="1320px">
      <thead class="bg-[var(--nx-surface-muted)] text-xs uppercase tracking-wide text-[var(--nx-text-muted)]"><tr><th class="px-4 py-3">Account</th><th class="px-4 py-3">Subscriber</th><th class="px-4 py-3">Plan</th><th class="px-4 py-3">PPP Username</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Connection</th><th class="px-4 py-3">Due / Expiry</th><th class="w-80 px-4 py-3 text-right">Actions</th></tr></thead>
      <tbody class="divide-y divide-[var(--nx-border)]"><tr v-if="loading"><td colspan="8" class="px-4 py-12 text-center text-[var(--nx-text-muted)]">Loading subscribers…</td></tr><tr v-else-if="!pagedRows.length"><td colspan="8" class="px-4 py-12 text-center"><p class="font-semibold">No subscribers found</p><p class="mt-1 text-[var(--nx-text-muted)]">Change the filters or add a subscriber.</p></td></tr><tr v-for="row in pagedRows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60"><td class="px-4 py-3 font-mono text-xs">{{ row.account_number || '—' }}</td><td class="px-4 py-3"><p class="font-semibold">{{ row.full_name }}</p><p class="text-xs text-[var(--nx-text-muted)]">{{ row.email || row.contact_number || 'No contact details' }}</p></td><td class="px-4 py-3">{{ row.plan_name || '—' }}</td><td class="px-4 py-3 font-mono text-xs">{{ row.ppp_username || '—' }}</td><td class="px-4 py-3"><NxBadge :tone="serviceTone(row.service_status)">{{ row.service_status || 'UNKNOWN' }}</NxBadge></td><td class="px-4 py-3"><NxBadge :tone="row.online ? 'success' : 'neutral'">{{ row.online ? 'ONLINE' : 'OFFLINE' }}</NxBadge></td><td class="px-4 py-3 text-xs">{{ row.account_type === 'PREPAID' ? (row.expires_at || '—') : (row.next_due_date || '—') }}</td><td class="px-4 py-3"><div class="flex justify-end gap-2 whitespace-nowrap"><button class="min-h-9 rounded-md border border-[var(--nx-border)] px-3 text-xs font-semibold" @click="openView(row)">View</button><button class="min-h-9 rounded-md bg-brand-600 px-3 text-xs font-semibold text-white" @click="openEdit(row)">Edit</button><button class="min-h-9 rounded-md border border-[var(--nx-border)] px-3 text-xs font-semibold" @click="pendingAction = { kind: row.service_status === 'SUSPENDED' ? 'reactivate' : 'suspend', subscriber: row }">{{ row.service_status === 'SUSPENDED' ? 'Reactivate' : 'Suspend' }}</button><button class="min-h-9 rounded-md border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300" @click="pendingAction = { kind: 'delete', subscriber: row }">Delete</button></div></td></tr></tbody>
      <template #footer><NxPagination :page="page" :total-pages="totalPages" :total-records="filtered.length" @previous="page--" @next="page++" /></template>
    </NxTableShell>

    <Teleport to="body">
      <div v-if="formOpen" class="nx-next-overlay fixed inset-0 z-[2000] grid place-items-center overflow-y-auto bg-slate-950/35 p-4" @click.self="formOpen = false"><form class="my-6 w-full max-w-3xl overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] text-[var(--nx-text)] shadow-2xl" @submit.prevent="submit"><header class="flex justify-between border-b border-[var(--nx-border)] px-6 py-4"><div><h2 class="text-lg font-bold">{{ form.id ? 'Edit Subscriber' : 'Add Subscriber' }}</h2><p class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ form.id ? 'Update account and plan information.' : 'Create the subscriber account and generated credentials.' }}</p></div><button type="button" aria-label="Close" @click="formOpen = false">✕</button></header><NxAlert v-if="dialogError" tone="danger" class="mx-6 mt-5">{{ dialogError }}</NxAlert><div class="grid gap-5 bg-[var(--nx-canvas)] p-6 md:grid-cols-2"><label class="grid gap-1.5 text-sm font-semibold">Full Name<input v-model="form.full_name" required class="min-h-11 rounded-md bg-[var(--nx-surface)] px-3 font-normal"></label><label class="grid gap-1.5 text-sm font-semibold">Email<input v-model="form.email" type="email" required class="min-h-11 rounded-md bg-[var(--nx-surface)] px-3 font-normal"></label><label class="grid gap-1.5 text-sm font-semibold">Contact Number<input v-model="form.contact_number" class="min-h-11 rounded-md bg-[var(--nx-surface)] px-3 font-normal"></label><label class="grid gap-1.5 text-sm font-semibold">Plan<select v-model="form.plan_id" required class="min-h-11 rounded-md bg-[var(--nx-surface)] px-3 font-normal"><option :value="0" disabled>Select plan</option><option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.plan_name }}</option></select></label><label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Address<textarea v-model="form.address" rows="3" class="rounded-md bg-[var(--nx-surface)] px-3 py-2 font-normal"></textarea></label></div><footer class="flex justify-end gap-2 border-t border-[var(--nx-border)] px-6 py-4"><NxButton type="button" variant="secondary" @click="formOpen = false">Cancel</NxButton><NxButton type="submit" :disabled="saving">{{ saving ? 'Saving…' : form.id ? 'Save Changes' : 'Create Subscriber' }}</NxButton></footer></form></div>

      <div v-if="viewSubscriber" class="nx-next-overlay fixed inset-0 z-[2000] flex justify-end bg-slate-950/30" @click.self="viewSubscriber = null"><aside class="h-full w-full max-w-2xl overflow-y-auto border-l border-[var(--nx-border)] bg-[var(--nx-surface)] p-6 text-[var(--nx-text)] shadow-2xl"><header class="flex justify-between"><div><p class="text-xs font-bold uppercase text-brand-600">Subscriber details</p><h2 class="mt-1 text-xl font-bold">{{ viewSubscriber.full_name }}</h2><p class="text-sm text-[var(--nx-text-muted)]">{{ viewSubscriber.account_number }}</p></div><button aria-label="Close" @click="viewSubscriber = null">✕</button></header><dl class="mt-6 grid gap-3 sm:grid-cols-2"><div v-for="item in [['Email',viewSubscriber.email || '—'],['Contact',viewSubscriber.contact_number || '—'],['Address',viewSubscriber.address || '—'],['Plan',viewSubscriber.plan_name || '—'],['Service Status',viewSubscriber.service_status || '—'],['PPP Username',viewSubscriber.ppp_username || '—'],['Service Number',viewSubscriber.service_number || '—'],['ACS Status',String(viewSubscriber.acs_status || '—')],['WAN IP',String(viewSubscriber.wan_ip || '—')],['NAP',viewSubscriber.nap_name || '—'],['ONT Serial',viewSubscriber.ont_serial || '—'],['Installation Date',viewSubscriber.installed_at || 'Not installed'],['S-VLAN',viewSubscriber.svlan || '—'],['C-VLAN',viewSubscriber.cvlan || '—']]" :key="String(item[0])" class="rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><dt class="text-xs font-semibold uppercase text-[var(--nx-text-muted)]">{{ item[0] }}</dt><dd class="mt-1 break-words font-semibold">{{ item[1] }}</dd></div></dl><div class="mt-6 flex flex-wrap gap-2"><NxButton variant="secondary" @click="pendingAction = { kind: 'reset-ppp', subscriber: viewSubscriber }; viewSubscriber = null">Reset PPP Password</NxButton><NxButton variant="secondary" @click="pendingAction = { kind: 'reset-portal', subscriber: viewSubscriber }; viewSubscriber = null">Reset Portal Password</NxButton></div></aside></div>

      <div v-if="credentials" class="nx-next-overlay fixed inset-0 z-[2000] grid place-items-center bg-slate-950/35 p-4"><div class="w-full max-w-xl rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] p-6 text-[var(--nx-text)] shadow-2xl"><h2 class="text-lg font-bold">Generated credentials</h2><p class="mt-1 text-sm text-[var(--nx-text-muted)]">Copy these credentials now. Passwords may not be shown again.</p><dl class="mt-5 grid gap-3 sm:grid-cols-2"><div v-for="item in [['Portal Username',credentials.portal_username],['Portal Password',credentials.portal_password],['PPP Username',credentials.ppp_username],['PPP Password',credentials.ppp_password],['Account Number',credentials.account_number],['Service Number',credentials.service_number]]" :key="String(item[0])" class="rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-3"><dt class="text-xs uppercase text-[var(--nx-text-muted)]">{{ item[0] }}</dt><dd class="mt-1 break-all font-mono font-semibold">{{ item[1] || '—' }}</dd></div></dl><div class="mt-6 flex justify-end"><NxButton @click="credentials = null">I have copied these</NxButton></div></div></div>
    </Teleport>
    <NxConfirmDialog :open="Boolean(pendingAction)" :title="actionCopy.title" :description="`${pendingAction?.subscriber.full_name || 'Subscriber'} — ${actionCopy.text}`" :confirm-label="actionCopy.confirm" :busy="saving" :danger="pendingAction?.kind === 'delete'" :error="dialogError" @close="pendingAction = null" @confirm="confirmAction" />
  </div>
</template>
