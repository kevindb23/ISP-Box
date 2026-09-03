<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxBadge from '../../components/ui/NxBadge.vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxConfirmDialog from '../../components/ui/NxConfirmDialog.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import NxPagination from '../../components/ui/NxPagination.vue';
import NxTableShell from '../../components/ui/NxTableShell.vue';
import NxToolbar from '../../components/ui/NxToolbar.vue';
import {
  createSubscriberPlan,
  deleteSubscriberPlan,
  listSubscriberPlans,
  updateSubscriberPlan,
} from './api';
import type { PlanType, SubscriberPlan, SubscriberPlanInput } from './contracts';

const props = defineProps<{ initialPlans: SubscriberPlan[] }>();
const rows = ref<SubscriberPlan[]>([...props.initialPlans]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const formError = ref('');
const deleteError = ref('');
const search = ref('');
const typeFilter = ref<'' | PlanType>('');
const statusFilter = ref<'' | 'ACTIVE' | 'INACTIVE'>('');
const page = ref(1);
const perPage = 10;
const formOpen = ref(false);
const viewPlan = ref<SubscriberPlan | null>(null);
const deletePlan = ref<SubscriberPlan | null>(null);

const form = reactive<SubscriberPlanInput & { id: number | null }>({
  id: null,
  plan_name: '',
  price: 0,
  description: null,
  plan_type: 'POSTPAID',
  validity_days: 30,
  speed_mbps: 25,
  is_active: 1,
});

const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const filtered = computed(() => {
  const query = search.value.trim().toLowerCase();
  return rows.value.filter((plan) => {
    const status = plan.is_active === 1 ? 'ACTIVE' : 'INACTIVE';
    const haystack = [plan.plan_name, plan.plan_type, plan.description, plan.speed_mbps, plan.price, status]
      .join(' ').toLowerCase();
    return (!query || haystack.includes(query))
      && (!typeFilter.value || plan.plan_type === typeFilter.value)
      && (!statusFilter.value || status === statusFilter.value);
  });
});
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const pagedRows = computed(() => {
  if (page.value > totalPages.value) page.value = totalPages.value;
  return filtered.value.slice((page.value - 1) * perPage, page.value * perPage);
});
const stats = computed(() => {
  const total = rows.value.length;
  return {
    total,
    prepaid: rows.value.filter((p) => p.plan_type === 'PREPAID').length,
    postpaid: rows.value.filter((p) => p.plan_type === 'POSTPAID').length,
    arpu: total ? rows.value.reduce((sum, p) => sum + Number(p.price || 0), 0) / total : 0,
  };
});

function resetMessages(): void { error.value = ''; notice.value = ''; }
function openCreate(): void {
  Object.assign(form, { id: null, plan_name: '', price: 0, description: null, plan_type: 'POSTPAID', validity_days: 30, speed_mbps: 25, is_active: 1 });
  resetMessages();
  formError.value = '';
  formOpen.value = true;
}
function openEdit(plan: SubscriberPlan): void {
  Object.assign(form, {
    id: plan.id, plan_name: plan.plan_name, price: Number(plan.price), description: plan.description,
    plan_type: plan.plan_type, validity_days: plan.validity_days, speed_mbps: plan.speed_mbps, is_active: plan.is_active,
  });
  resetMessages();
  formError.value = '';
  formOpen.value = true;
}
function closeOverlays(): void {
  formOpen.value = false;
  viewPlan.value = null;
  deletePlan.value = null;
}
async function refresh(showNotice = false): Promise<void> {
  loading.value = true;
  resetMessages();
  try {
    rows.value = await listSubscriberPlans();
    if (showNotice) notice.value = 'Plans refreshed.';
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Failed to load plans.';
  } finally { loading.value = false; }
}
async function submit(): Promise<void> {
  formError.value = '';
  if (!form.plan_name.trim()) { formError.value = 'Plan name is required.'; return; }
  if (form.speed_mbps <= 0) { formError.value = 'Speed must be greater than 0.'; return; }
  if (form.price < 0) { formError.value = 'Price cannot be negative.'; return; }
  saving.value = true;
  try {
    const input: SubscriberPlanInput = { ...form, plan_name: form.plan_name.trim(), validity_days: form.plan_type === 'POSTPAID' ? 30 : form.validity_days };
    const message = form.id ? await updateSubscriberPlan(form.id, input) : await createSubscriberPlan(input);
    formOpen.value = false;
    await refresh(false);
    notice.value = message || (form.id ? 'Plan updated successfully.' : 'Plan created successfully.');
  } catch (reason) {
    formError.value = reason instanceof Error ? reason.message : 'Failed to save plan.';
  } finally { saving.value = false; }
}
async function confirmDelete(): Promise<void> {
  if (!deletePlan.value) return;
  saving.value = true;
  deleteError.value = '';
  try {
    const message = await deleteSubscriberPlan(deletePlan.value.id);
    deletePlan.value = null;
    await refresh(false);
    notice.value = message || 'Plan deleted successfully.';
  } catch (reason) {
    deleteError.value = reason instanceof Error ? reason.message : 'Failed to delete plan.';
  } finally { saving.value = false; }
}

watch([search, typeFilter, statusFilter], () => { page.value = 1; });
watch(deletePlan, () => { deleteError.value = ''; });

onMounted(() => { if (!rows.value.length) void refresh(false); });
</script>

<template>
  <div class="space-y-4 pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Commercial catalog" title="Plan inventory" description="Commercial service profiles for subscriber activation, billing, and provisioning.">
      <template #actions>
        <NxButton class="shrink-0" @click="openCreate">+ New Plan</NxButton>
        <NxButton variant="secondary" :disabled="loading" @click="refresh(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton>
      </template>
    </NxPageHeader>

    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <NxCard v-for="item in [
        ['Total Plans', stats.total, 'Commercial packages configured'],
        ['Postpaid', stats.postpaid, 'Recurring commercial plans'],
        ['Prepaid', stats.prepaid, 'Time-bound service plans'],
        ['Average ARPU', money.format(stats.arpu), 'Average catalog price'],
      ]" :key="String(item[0])">
        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--nx-text-muted)]">{{ item[0] }}</p>
        <p class="mt-2 text-2xl font-bold">{{ item[1] }}</p>
        <p class="mt-1 text-xs text-[var(--nx-text-muted)]">{{ item[2] }}</p>
      </NxCard>
    </section>

    <NxToolbar>
      <div class="grid gap-3 lg:grid-cols-[minmax(280px,1fr)_minmax(300px,360px)]">
        <label class="relative block">
          <span class="sr-only">Search plans</span>
          <input v-model="search" type="search" placeholder="Search plan, type, speed, price or status…" class="min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] px-4 text-sm text-[var(--nx-text)] outline-none placeholder:text-[var(--nx-text-muted)] focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        </label>
        <div class="grid grid-cols-2 gap-3">
          <select v-model="typeFilter" class="min-h-11 min-w-0 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] px-3 text-sm text-[var(--nx-text)]"><option value="">All Types</option><option value="PREPAID">Prepaid</option><option value="POSTPAID">Postpaid</option></select>
          <select v-model="statusFilter" class="min-h-11 min-w-0 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] px-3 text-sm text-[var(--nx-text)]"><option value="">All Statuses</option><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select>
        </div>
      </div>
    </NxToolbar>

    <NxTableShell min-width="1120px">
          <thead class="bg-[var(--nx-surface-muted)] text-xs uppercase tracking-wide text-[var(--nx-text-muted)]">
            <tr><th class="px-4 py-3">Plan</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Validity</th><th class="px-4 py-3">Speed</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Description</th><th class="w-64 px-4 py-3 text-right">Actions</th></tr>
          </thead>
          <tbody class="divide-y divide-[var(--nx-border)]">
            <tr v-if="loading"><td colspan="8" class="px-4 py-12 text-center text-[var(--nx-text-muted)]">Loading plans…</td></tr>
            <tr v-else-if="!pagedRows.length"><td colspan="8" class="px-4 py-12 text-center"><p class="font-semibold">No plans found</p><p class="mt-1 text-sm text-[var(--nx-text-muted)]">Change the filters or create a new plan.</p></td></tr>
            <tr v-for="plan in pagedRows" :key="plan.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
              <td class="px-4 py-3 font-semibold">{{ plan.plan_name }}</td>
              <td class="px-4 py-3"><NxBadge :tone="plan.plan_type === 'PREPAID' ? 'warning' : 'info'">{{ plan.plan_type }}</NxBadge></td>
              <td class="px-4 py-3 whitespace-nowrap">{{ plan.validity_days }} days</td>
              <td class="px-4 py-3 whitespace-nowrap font-mono">{{ plan.speed_mbps }} Mbps</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ money.format(plan.price) }}</td>
              <td class="px-4 py-3"><NxBadge :tone="plan.is_active ? 'success' : 'neutral'">{{ plan.is_active ? 'ACTIVE' : 'INACTIVE' }}</NxBadge></td>
              <td class="max-w-xs px-4 py-3 text-[var(--nx-text-muted)]">{{ plan.description || '—' }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                  <button type="button" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-xs font-semibold text-[var(--nx-text)] transition-colors hover:bg-[var(--nx-surface-muted)]" @click="viewPlan = plan">View</button>
                  <button type="button" class="inline-flex min-h-9 items-center justify-center rounded-md bg-brand-600 px-3 text-xs font-semibold text-white transition-colors hover:bg-brand-700" @click="openEdit(plan)">Edit</button>
                  <button type="button" class="inline-flex min-h-9 items-center justify-center rounded-md border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300 dark:hover:bg-red-950" @click="deletePlan = plan">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
      <template #footer>
        <NxPagination :page="page" :total-pages="totalPages" :total-records="filtered.length" @previous="page--" @next="page++" />
      </template>
    </NxTableShell>

    <Teleport to="body">
      <div v-if="formOpen" class="nx-next-overlay fixed inset-0 z-[2000] grid place-items-center overflow-y-auto bg-slate-950/35 p-4" @click.self="formOpen = false">
        <form class="my-6 w-full max-w-3xl overflow-hidden rounded-lg border border-[var(--nx-border)] bg-[var(--nx-surface)] text-[var(--nx-text)] shadow-2xl" @submit.prevent="submit">
          <header class="flex items-start justify-between border-b border-[var(--nx-border)] px-6 py-4"><div><h2 class="text-lg font-bold">{{ form.id ? 'Edit Subscriber Plan' : 'Add Subscriber Plan' }}</h2><p class="mt-1 text-sm text-[var(--nx-text-muted)]">{{ form.id ? 'Update the commercial plan settings.' : 'Create a commercial service profile.' }}</p></div><button type="button" aria-label="Close" class="rounded p-2 hover:bg-[var(--nx-surface-muted)]" @click="formOpen = false">✕</button></header>
          <NxAlert v-if="formError" tone="danger" class="mx-6 mt-5">{{ formError }}</NxAlert>
          <div class="grid gap-5 bg-[var(--nx-canvas)] p-6 md:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-semibold">Plan Name<input v-model="form.plan_name" :readonly="Boolean(form.id)" required class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal outline-none focus:border-brand-500"></label>
            <label class="grid gap-1.5 text-sm font-semibold">Description<textarea v-model="form.description" rows="3" class="rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 py-2 font-normal outline-none focus:border-brand-500"></textarea></label>
            <label class="grid gap-1.5 text-sm font-semibold">Plan Type<select v-model="form.plan_type" class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal" @change="form.validity_days = form.plan_type === 'POSTPAID' ? 30 : form.validity_days"><option value="POSTPAID">Postpaid</option><option value="PREPAID">Prepaid</option></select></label>
            <label class="grid gap-1.5 text-sm font-semibold">Validity<select v-model="form.validity_days" :disabled="form.plan_type === 'POSTPAID'" class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal disabled:opacity-60"><option :value="30">30 Days</option><option :value="14">14 Days</option><option :value="7">7 Days</option></select></label>
            <label class="grid gap-1.5 text-sm font-semibold">Price<input v-model.number="form.price" type="number" min="0" step="0.01" required class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal"></label>
            <label class="grid gap-1.5 text-sm font-semibold">Speed (Mbps)<input v-model.number="form.speed_mbps" type="number" min="1" required class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal"></label>
            <label class="grid gap-1.5 text-sm font-semibold">Status<select v-model="form.is_active" class="min-h-11 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 font-normal"><option :value="1">Active</option><option :value="0">Inactive</option></select></label>
          </div>
          <footer class="flex justify-end gap-2 border-t border-[var(--nx-border)] px-6 py-4"><NxButton type="button" variant="secondary" @click="formOpen = false">Cancel</NxButton><NxButton type="submit" :disabled="saving">{{ saving ? 'Saving…' : (form.id ? 'Save Changes' : 'Create Plan') }}</NxButton></footer>
        </form>
      </div>

      <div v-if="viewPlan" class="nx-next-overlay fixed inset-0 z-[2000] flex justify-end bg-slate-950/30" @click.self="viewPlan = null">
        <aside class="h-full w-full max-w-xl overflow-y-auto border-l border-[var(--nx-border)] bg-[var(--nx-surface)] p-6 text-[var(--nx-text)] shadow-2xl">
          <header class="flex items-start justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-brand-600">Plan details</p><h2 class="mt-1 text-xl font-bold">{{ viewPlan.plan_name }}</h2></div><button aria-label="Close" class="rounded p-2 hover:bg-[var(--nx-surface-muted)]" @click="viewPlan = null">✕</button></header>
          <dl class="mt-6 grid gap-3 sm:grid-cols-2"><div v-for="item in [['Plan Type',viewPlan.plan_type],['Validity',`${viewPlan.validity_days} days`],['Speed',`${viewPlan.speed_mbps} Mbps`],['Price',money.format(viewPlan.price)],['Status',viewPlan.is_active ? 'ACTIVE' : 'INACTIVE'],['Description',viewPlan.description || '—']]" :key="String(item[0])" class="rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><dt class="text-xs font-semibold uppercase text-[var(--nx-text-muted)]">{{ item[0] }}</dt><dd class="mt-1 font-semibold">{{ item[1] }}</dd></div></dl>
        </aside>
      </div>

    </Teleport>
    <NxConfirmDialog :open="Boolean(deletePlan)" title="Delete plan?" :description="`Delete ${deletePlan?.plan_name || 'this plan'}? The backend will reject deletion if active subscribers use this plan.`" confirm-label="Delete Plan" :busy="saving" danger :error="deleteError" @close="deletePlan = null" @confirm="confirmDelete" />
  </div>
</template>
