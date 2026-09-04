<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import { getJson, postForm } from '../../lib/api';

type EmailSettings = {
  preset: string;
  smtp_host: string;
  smtp_port: number | string;
  smtp_encryption: 'NONE' | 'TLS' | 'SSL';
  smtp_username: string;
  from_name: string;
  from_email: string;
  smtp_password_configured?: boolean;
};
type Preset = { label: string; smtp_host: string; smtp_port: number; smtp_encryption: string; webmail?: string };

const blank = (): EmailSettings => ({ preset: 'CUSTOM', smtp_host: '', smtp_port: 587, smtp_encryption: 'TLS', smtp_username: '', from_name: '', from_email: '' });
const form = ref<EmailSettings>(blank());
const original = ref<EmailSettings>(blank());
const presets = ref<Record<string, Preset>>({});
const password = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const testRecipient = ref('');
const inputClass = 'min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)] outline-none transition focus:border-[var(--nx-primary)]';
const dirty = computed(() => JSON.stringify(form.value) !== JSON.stringify(original.value) || !!password.value);

async function load(show = false) {
  loading.value = true; error.value = ''; notice.value = '';
  try {
    const data = await getJson<{ settings: EmailSettings; presets: Record<string, Preset> }>('/api/v1/email');
    form.value = { ...blank(), ...data.settings }; original.value = { ...form.value }; presets.value = data.presets || {}; password.value = '';
    if (show) notice.value = 'Email settings refreshed.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load email settings.'; }
  finally { loading.value = false; }
}

function applyPreset() {
  const preset = presets.value[form.value.preset];
  if (!preset || form.value.preset === 'CUSTOM') return;
  form.value.smtp_host = preset.smtp_host; form.value.smtp_port = preset.smtp_port;
  form.value.smtp_encryption = preset.smtp_encryption as EmailSettings['smtp_encryption'];
}

async function save() {
  saving.value = true; error.value = ''; notice.value = '';
  try {
    const body = new FormData();
    Object.entries(form.value).forEach(([key, value]) => body.set(key, String(value ?? '')));
    body.set('smtp_password', password.value);
    const response = await postForm<EmailSettings>('/api/v1/email', body);
    form.value = { ...blank(), ...response.data }; original.value = { ...form.value }; password.value = '';
    notice.value = response.message || 'Email settings saved.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to save email settings.'; }
  finally { saving.value = false; }
}

async function testConnection() {
  saving.value = true; error.value = ''; notice.value = '';
  try {
    const response = await postForm<{ connected: boolean; host: string; port: number }>('/api/v1/email/test', new FormData());
    notice.value = response.message || ('SMTP connection to ' + response.data.host + ':' + response.data.port + ' succeeded.');
  } catch (e) { error.value = e instanceof Error ? e.message : 'SMTP connection failed.'; }
  finally { saving.value = false; }
}

async function sendTestEmail() {
  saving.value = true; error.value = ''; notice.value = '';
  try {
    const body = new FormData();
    body.set('recipient', testRecipient.value);
    const response = await postForm<{ sent: boolean; recipient: string }>('/api/v1/email/test-email', body);
    notice.value = response.message || 'Test email sent successfully.';
  } catch (e) { error.value = e instanceof Error ? e.message : 'Test email could not be sent.'; }
  finally { saving.value = false; }
}

onMounted(() => void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Admin / Email" title="Email delivery" description="Configure the local SMTP server used by NexusBox.">
      <template #actions>
        <NxButton variant="secondary" :disabled="loading || saving" @click="load(true)">{{ loading ? 'Refreshing…' : 'Refresh' }}</NxButton>
        <NxButton variant="secondary" :disabled="loading || saving" @click="testConnection">{{ saving ? 'Testing…' : 'Test connection' }}</NxButton>
        <NxButton type="submit" form="emailForm" :disabled="loading || saving || !dirty">{{ saving ? 'Saving…' : 'Save settings' }}</NxButton>
      </template>
    </NxPageHeader>
    <NxAlert v-if="error || notice" :tone="error ? 'danger' : 'success'">{{ error || notice }}</NxAlert>
    <form id="emailForm" class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.8fr)]" @submit.prevent="save">
      <NxCard title="SMTP server" description="Use a preset to populate standard provider settings, then enter your account credentials.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Provider preset
            <select v-model="form.preset" :class="inputClass" @change="applyPreset">
              <option v-for="(preset, key) in presets" :key="key" :value="key">{{ preset.label }}</option>
            </select>
          </label>
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">SMTP host<input v-model="form.smtp_host" required :class="inputClass"></label>
          <label class="grid gap-1.5 text-sm font-semibold">Port<input v-model="form.smtp_port" type="number" min="1" max="65535" required :class="inputClass"></label>
          <label class="grid gap-1.5 text-sm font-semibold">Encryption<select v-model="form.smtp_encryption" :class="inputClass"><option value="TLS">TLS / STARTTLS</option><option value="SSL">SSL</option><option value="NONE">None</option></select></label>
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Username<input v-model="form.smtp_username" autocomplete="username" :class="inputClass"></label>
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Password<input v-model="password" type="password" autocomplete="new-password" :placeholder="form.smtp_password_configured ? 'Configured — enter only to replace' : 'Enter SMTP password'" :class="inputClass"><small class="font-normal text-[var(--nx-text-muted)]">Leave blank to keep the saved password.</small></label>
        </div>
      </NxCard>
      <NxCard title="Sender identity" description="These values appear in outgoing NexusBox messages.">
        <div class="grid gap-4">
          <label class="grid gap-1.5 text-sm font-semibold">From name<input v-model="form.from_name" placeholder="NexusBox" :class="inputClass"></label>
          <label class="grid gap-1.5 text-sm font-semibold">From email<input v-model="form.from_email" type="email" placeholder="noreply@example.com" :class="inputClass"></label>
        </div>
        <NxAlert class="mt-5" tone="info">Gmail usually requires an app password. Email hosting uses <b>secure.emailsrvr.com</b> with SSL port 465 or TLS port 587.</NxAlert>
        <div class="mt-5 border-t border-[var(--nx-border)] pt-5">
          <label class="grid gap-1.5 text-sm font-semibold">Test email recipient
            <input v-model="testRecipient" type="email" required placeholder="you@example.com" :class="inputClass">
            <small class="font-normal text-[var(--nx-text-muted)]">Sends a real test message using the current saved SMTP settings.</small>
          </label>
          <NxButton class="mt-4" variant="secondary" :disabled="loading || saving || !testRecipient" @click="sendTestEmail">{{ saving ? 'Sending…' : 'Send test email' }}</NxButton>
        </div>
      </NxCard>
    </form>
  </div>
</template>

<style scoped>:global([data-nx-next-root="email"]){max-width:100%;overflow-x:hidden}</style>
