<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import NxSwitch from '../../components/ui/NxSwitch.vue';
import { getJson, postForm } from '../../lib/api';

type NotificationSettings = { enabled: boolean; telegram_chat_id: string; telegram_bot_configured?: boolean };
const form = ref<NotificationSettings>({ enabled: false, telegram_chat_id: '' });
const original = ref<NotificationSettings>({ enabled: false, telegram_chat_id: '' });
const token = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const inputClass = 'min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)] outline-none transition focus:border-[var(--nx-primary)]';
const dirty = computed(() => JSON.stringify(form.value) !== JSON.stringify(original.value) || !!token.value);

async function load(show = false) {
  loading.value = true; error.value = ''; notice.value = '';
  try {
    const data = await getJson<NotificationSettings>('/api/v1/notifications/settings');
    form.value = { enabled: !!data.enabled, telegram_chat_id: data.telegram_chat_id || '', telegram_bot_configured: data.telegram_bot_configured };
    original.value = { ...form.value }; token.value = '';
    if (show) notice.value = 'Notification settings refreshed.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load notification settings.'; }
  finally { loading.value = false; }
}

async function save() {
  saving.value = true; error.value = ''; notice.value = '';
  try {
    const body = new FormData();
    body.set('enabled', form.value.enabled ? '1' : '0');
    body.set('telegram_bot_token', token.value);
    body.set('telegram_chat_id', form.value.telegram_chat_id);
    const response = await postForm<NotificationSettings>('/api/v1/notifications/settings', body);
    form.value = { ...form.value, ...response.data }; original.value = { ...form.value }; token.value = '';
    notice.value = response.message || 'Notification settings saved.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to save notification settings.'; }
  finally { saving.value = false; }
}

async function sendTest() {
  saving.value = true; error.value = ''; notice.value = '';
  try {
    const response = await postForm<{ sent: boolean }>('/api/v1/notifications/test', new FormData());
    notice.value = response.message || 'Telegram test notification sent.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Telegram test notification failed.'; }
  finally { saving.value = false; }
}

onMounted(() => void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Admin / Notifications" title="Notifications" description="Connect Telegram for operational alerts. Notifications are disabled by default.">
      <template #actions>
        <NxButton variant="secondary" :disabled="loading || saving" @click="load(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton>
        <NxButton variant="secondary" :disabled="loading || saving" @click="sendTest">{{ saving ? 'Sending…' : 'Send test' }}</NxButton>
        <NxButton type="submit" form="notificationsForm" :disabled="loading || saving || !dirty">{{ saving ? 'Saving…' : 'Save settings' }}</NxButton>
      </template>
    </NxPageHeader>
    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>
    <form id="notificationsForm" @submit.prevent="save">
      <NxCard title="Telegram notifications" description="Configure a Telegram bot for operational alerts. This integration is disabled until you enable it and save valid credentials.">
        <div class="grid gap-5">
          <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4">
            <div><p class="font-bold">Enable Telegram notifications</p><p class="text-sm text-[var(--nx-text-muted)]">Notifications are disabled by default.</p></div>
            <NxSwitch v-model="form.enabled" label="Telegram notifications" :disabled="saving" />
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-semibold">Bot token<input v-model="token" type="password" autocomplete="new-password" :placeholder="form.telegram_bot_configured ? 'Configured — enter only to replace' : 'Enter Telegram bot token'" :class="inputClass"><small class="font-normal text-[var(--nx-text-muted)]">Leave blank to keep the saved token.</small></label>
            <label class="grid gap-1.5 text-sm font-semibold">Chat ID<input v-model="form.telegram_chat_id" placeholder="-1001234567890" :class="inputClass"><small class="font-normal text-[var(--nx-text-muted)]">The chat or channel where alerts should be sent.</small></label>
          </div>
        </div>
      </NxCard>
    </form>
  </div>
</template>

<style scoped>:global([data-nx-next-root="notifications"]){max-width:100%;overflow-x:hidden}</style>
