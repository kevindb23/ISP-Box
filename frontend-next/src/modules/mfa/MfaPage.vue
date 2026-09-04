<script setup lang="ts">
import { onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxBadge from '../../components/ui/NxBadge.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import NxTableShell from '../../components/ui/NxTableShell.vue';
import { getJson, postForm } from '../../lib/api';

type User = { id: number; username: string; full_name?: string; role: string; email?: string; mfa_enabled?: number; verified_at?: string | null; mfa_method?: string };
const users = ref<User[]>([]); const loading = ref(false); const error = ref(''); const notice = ref(''); const resetting = ref<number | null>(null);
const enabled = (user: User) => Number(user.mfa_enabled) === 1 && !!user.verified_at;
async function load() { loading.value = true; error.value = ''; try { const data = await getJson<{ users: User[] }>('/api/v1/mfa'); users.value = data.users || []; } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load MFA settings.'; } finally { loading.value = false; } }
async function reset(user: User) { if (!window.confirm(`Reset MFA for ${user.full_name || user.username}?`)) return; resetting.value = user.id; error.value = ''; notice.value = ''; try { const response = await postForm(`/api/v1/mfa/${user.id}/reset`, new FormData()); notice.value = response.message || 'MFA reset successfully.'; await load(); } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to reset MFA.'; } finally { resetting.value = null; } }
onMounted(() => void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Account security" title="Multi-factor authentication" description="Enable a second verification step for staff and subscriber accounts."><template #actions><NxBadge tone="neutral">Disabled by default</NxBadge></template></NxPageHeader>
    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>
    <div class="grid gap-4 md:grid-cols-2"><NxCard title="Authenticator app" description="Scan a QR code or enter the setup key manually."><div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-brand-600"><i class="bi bi-phone" aria-hidden="true" /></div></NxCard><NxCard title="Email OTP" description="Send a short-lived verification code to the account email."><div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-brand-600"><i class="bi bi-envelope" aria-hidden="true" /></div></NxCard></div>
    <NxCard title="Account coverage" description="Configure MFA for every account type, including subscriber portal users."><template #actions><NxButton variant="secondary" :disabled="loading" @click="load">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton></template><NxTableShell min-width="860px"><thead class="bg-[var(--nx-surface-muted)] text-xs uppercase"><tr><th class="px-4 py-3">Account</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody><tr v-if="loading && !users.length"><td colspan="6" class="px-4 py-12 text-center text-[var(--nx-text-muted)]">Loading security settings…</td></tr><tr v-else-if="!users.length"><td colspan="6" class="px-4 py-12 text-center text-[var(--nx-text-muted)]">No user accounts found.</td></tr><tr v-for="user in users" :key="user.id"><td class="px-4 py-3"><p class="font-semibold">{{ user.full_name || user.username }}</p><p class="text-xs text-[var(--nx-text-muted)]">@{{ user.username }}</p></td><td class="px-4 py-3">{{ user.role }}</td><td class="px-4 py-3">{{ user.email || 'No email' }}</td><td class="px-4 py-3">{{ user.mfa_method === 'EMAIL' ? 'Email OTP' : 'Authenticator' }}</td><td class="px-4 py-3"><NxBadge :tone="enabled(user) ? 'success' : 'neutral'">{{ enabled(user) ? 'Enabled' : 'Disabled' }}</NxBadge></td><td class="px-4 py-3 text-right"><NxButton variant="secondary" :disabled="resetting === user.id" @click="reset(user)">{{ resetting === user.id ? 'Resetting…' : 'Reset MFA' }}</NxButton></td></tr></tbody></NxTableShell></NxCard>
  </div>
</template>
