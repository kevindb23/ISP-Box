<script setup lang="ts">
import{computed,onMounted,ref}from'vue';import NxAlert from'../../components/ui/NxAlert.vue';import NxBadge from'../../components/ui/NxBadge.vue';import NxButton from'../../components/ui/NxButton.vue';import NxCard from'../../components/ui/NxCard.vue';import NxConfirmDialog from'../../components/ui/NxConfirmDialog.vue';import NxModal from'../../components/ui/NxModal.vue';import NxPageHeader from'../../components/ui/NxPageHeader.vue';import NxTableShell from'../../components/ui/NxTableShell.vue';import{get,post}from'./api';
type Token={id:number;name:string;description?:string;purpose?:string;scopes?:string[];transport_policy?:'HTTPS'|'HTTP'|'BOTH';created_at?:string;last_used_at?:string;last_used_ip?:string;expires_at?:string;revoked_at?:string;status?:string};type Identity={instance_id?:string;monitoring_client_id?:string;monitoring_client_name?:string;monitoring_instance_name?:string;monitoring_location?:string;monitoring_environment?:string};const props=withDefaults(defineProps<{initialTokens?:Token[];initialIdentity?:Identity}>(),{initialTokens:()=>[],initialIdentity:()=>({})});const rows=ref([...props.initialTokens]),identity=ref<Identity>({...props.initialIdentity,monitoring_environment:props.initialIdentity.monitoring_environment||'PRODUCTION'}),createOpen=ref(false),confirmOpen=ref(false),target=ref<Token|null>(null),busy=ref(false),identityBusy=ref(false),error=ref(''),notice=ref(''),secret=ref(''),copied=ref(false),form=ref({name:'',purpose:'CENTRAL_MONITORING',description:'',expires_at:'',transport_policy:'HTTPS' as 'HTTPS'|'HTTP'|'BOTH',scopes:['infrastructure.monitoring.read']as string[]});const scopes=['infrastructure.monitoring.read'];const inputClass='min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)]';const active=computed(()=>rows.value.filter(r=>String(r.status).toUpperCase()==='ACTIVE').length);const neverUsed=computed(()=>rows.value.filter(r=>!r.last_used_at).length);const fmt=(v?:string)=>v?new Date(v.replace(' ','T')).toLocaleString('en-PH'):'Never';
type MonitoringSettings={monitoring_hq_enabled:boolean;monitoring_hq_url:string;monitoring_allow_insecure_http:boolean;monitoring_verify_tls:boolean;monitoring_retention_days:number;monitoring_disk_warning_percent:number;monitoring_disk_critical_percent:number;monitoring_memory_warning_percent:number;monitoring_heartbeat_seconds:number};type Operations={settings:MonitoringSettings;configuration:{enabled:boolean;endpoint_configured:boolean;token_configured:boolean;ready:boolean;effective_url:string;transport:string;secure_transport:boolean;verify_tls:boolean};delivery:{pending:number;delivered:number;failed:number;last_delivered_at?:string};latest_snapshot?:any;history:{snapshots:any[];events:any[]}};const operations=ref<Operations|null>(null),operationsBusy=ref(false),settingsBusy=ref(false),testBusy=ref(false),retryBusy=ref(false);const serviceRows=computed(()=>Object.entries(operations.value?.latest_snapshot?.services||{}));const statusTone=(s?:string)=>s==='HEALTHY'||s==='ONLINE'?'success':s==='DEGRADED'?'warning':s==='CRITICAL'||s==='OFFLINE'?'danger':'neutral';
function openCreate(){form.value={name:'',purpose:'CENTRAL_MONITORING',description:'',expires_at:'',transport_policy:'HTTPS',scopes:['infrastructure.monitoring.read']};error.value='';createOpen.value=true;}async function create(){if(!form.value.name.trim()){error.value='Token name is required.';return;}if(!form.value.scopes.length){error.value='Select at least one read-only scope.';return;}busy.value=true;error.value='';try{const r=await post<any>('/api/v1/api-tokens/create',{...form.value,expires_at:form.value.expires_at||null});secret.value=String(r.data.token||'');createOpen.value=false;notice.value='Token created. Copy the secret now; it will not be shown again.';}catch(e){error.value=e instanceof Error?e.message:'Unable to create token.';}finally{busy.value=false;}}async function saveIdentity(){identityBusy.value=true;error.value='';try{const r=await post<Identity>('/api/v1/api-tokens/monitoring-identity',identity.value as Record<string,unknown>);identity.value={...r.data};notice.value='HQ monitoring identity saved.';}catch(e){error.value=e instanceof Error?e.message:'Unable to save monitoring identity.';}finally{identityBusy.value=false;}}function askRevoke(row:Token){target.value=row;confirmOpen.value=true;error.value='';}async function revoke(){if(!target.value)return;busy.value=true;try{await post('/api/v1/api-tokens/'+target.value.id+'/revoke');rows.value=rows.value.map(r=>r.id===target.value?.id?{...r,status:'REVOKED',revoked_at:new Date().toISOString()}:r);confirmOpen.value=false;notice.value='API token revoked.';}catch(e){error.value=e instanceof Error?e.message:'Unable to revoke token.';}finally{busy.value=false;}}async function copy(){await navigator.clipboard.writeText(secret.value);copied.value=true;setTimeout(()=>copied.value=false,1800);}
async function loadOperations(show=false){operationsBusy.value=true;error.value='';try{const r=await get<Operations>('/api/v1/api-tokens/monitoring-operations');operations.value=r.data;if(show)notice.value='Infrastructure monitoring refreshed.';}catch(e){error.value=e instanceof Error?e.message:'Unable to load monitoring operations.';}finally{operationsBusy.value=false;}}async function saveMonitoring(){if(!operations.value)return;settingsBusy.value=true;error.value='';try{const r=await post<Operations>('/api/v1/api-tokens/monitoring-settings',operations.value.settings as unknown as Record<string,unknown>);operations.value=r.data;notice.value='Monitoring settings saved.';}catch(e){error.value=e instanceof Error?e.message:'Unable to save monitoring settings.';}finally{settingsBusy.value=false;}}async function testHq(){testBusy.value=true;error.value='';try{const r=await post<any>('/api/v1/api-tokens/monitoring-test');notice.value=r.data.reachable?'HQ health endpoint is reachable.':'HQ test failed: '+(r.data.error_code||'unreachable');}catch(e){error.value=e instanceof Error?e.message:'HQ test failed.';}finally{testBusy.value=false;}}async function retryFailed(){retryBusy.value=true;error.value='';try{const r=await post<any>('/api/v1/api-tokens/monitoring-retry');notice.value=String(r.data.retried)+' failed deliveries queued for retry.';await loadOperations();}catch(e){error.value=e instanceof Error?e.message:'Unable to retry deliveries.';}finally{retryBusy.value=false;}}onMounted(()=>void loadOperations());
</script>
<template>
<div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
<NxPageHeader eyebrow="Integration security" title="API Tokens" description="Issue scoped credentials for external monitoring and integration clients.">
<template #actions>
<NxButton @click="openCreate">Create Token</NxButton>
</template>
</NxPageHeader>
<NxAlert v-if="error||notice" :tone="error?'danger':'success'">{{error||notice}}</NxAlert>
<NxAlert v-if="secret" tone="warning">
<b>Copy this token now.</b> It will not be displayed again.<div class="mt-3 flex gap-2">
<input :value="secret" readonly class="min-w-0 flex-1 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 py-2 font-mono text-xs text-[var(--nx-text)]">
<NxButton variant="secondary" @click="copy">{{copied?'Copied':'Copy'}}</NxButton>
</div>
</NxAlert>
<section class="grid gap-3 sm:grid-cols-3">
<NxCard>
<p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Total tokens</p>
<p class="mt-2 text-2xl font-bold">{{rows.length}}</p>
</NxCard>
<NxCard>
<p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Active</p>
<p class="mt-2 text-2xl font-bold">{{active}}</p>
</NxCard>
<NxCard>
<p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Never used</p>
<p class="mt-2 text-2xl font-bold">{{neverUsed}}</p>
</NxCard>
</section>
<NxCard>
  <div class="border-b border-[var(--nx-border)] pb-4">
    <h2 class="text-lg font-bold">HQ monitoring identity</h2>
    <p class="mt-1 text-sm text-[var(--nx-text-muted)]">Identifies this deployment in the HQ fleet dashboard. Subscriber and commercial information are excluded.</p>
  </div>
  <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <label class="grid gap-1 text-sm font-semibold">Client ID<input v-model="identity.monitoring_client_id" placeholder="CLIENT-0001" :class="inputClass"></label>
    <label class="grid gap-1 text-sm font-semibold">Client name<input v-model="identity.monitoring_client_name" placeholder="Client organization" :class="inputClass"></label>
    <label class="grid gap-1 text-sm font-semibold">Instance name<input v-model="identity.monitoring_instance_name" placeholder="Manila Primary" :class="inputClass"></label>
    <label class="grid gap-1 text-sm font-semibold">Location<input v-model="identity.monitoring_location" placeholder="Manila" :class="inputClass"></label>
    <label class="grid gap-1 text-sm font-semibold">Environment<select v-model="identity.monitoring_environment" :class="inputClass"><option>PRODUCTION</option><option>STAGING</option><option>TEST</option><option>DR</option></select></label>
    <label class="grid gap-1 text-sm font-semibold">Permanent instance ID<input :value="identity.instance_id||'Generated during setup'" readonly :class="inputClass"></label>
  </div>
  <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><code class="text-xs text-[var(--nx-text-muted)]">GET /api/v1/monitoring/infrastructure/snapshot</code><NxButton :disabled="identityBusy" @click="saveIdentity">{{identityBusy?'Saving…':'Save Identity'}}</NxButton></div>
</NxCard>
<NxCard>
  <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--nx-border)] pb-4">
    <div><h2 class="text-lg font-bold">Infrastructure monitoring</h2><p class="mt-1 text-sm text-[var(--nx-text-muted)]">Local service health, incident history, and privacy-safe HQ heartbeat delivery.</p></div>
    <div class="flex flex-wrap gap-2"><NxButton variant="secondary" :disabled="operationsBusy" @click="loadOperations(true)">{{operationsBusy?'Refreshing…':'Refresh'}}</NxButton><NxButton variant="secondary" :disabled="testBusy" @click="testHq">{{testBusy?'Testing…':'Test HQ'}}</NxButton></div>
  </div>
  <div v-if="operations" class="mt-4 space-y-5">
    <NxAlert v-if="operations.configuration.transport==='HTTP'" tone="warning">Monitoring delivery is using approved private HTTP. Heartbeats are authenticated and signed but not encrypted; migrate this endpoint to HTTPS before Internet exposure.</NxAlert>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <div class="rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Overall health</p><div class="mt-2"><NxBadge :tone="statusTone(operations.latest_snapshot?.overall_status)">{{operations.latest_snapshot?.overall_status||'NO DATA'}}</NxBadge></div><p class="mt-2 text-xs text-[var(--nx-text-muted)]">{{fmt(operations.latest_snapshot?.collected_at)}}</p></div>
      <div class="rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">HQ readiness</p><div class="mt-2"><NxBadge :tone="operations.configuration.ready?'success':'warning'">{{operations.configuration.ready?'READY':'INCOMPLETE'}}</NxBadge></div><p class="mt-2 text-xs text-[var(--nx-text-muted)]">Token {{operations.configuration.token_configured?'configured':'missing'}}</p></div>
      <div class="rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Pending delivery</p><p class="mt-2 text-2xl font-bold">{{operations.delivery.pending}}</p></div>
      <div class="rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Delivered</p><p class="mt-2 text-2xl font-bold">{{operations.delivery.delivered}}</p></div>
      <div class="rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-4"><p class="text-xs font-bold uppercase text-[var(--nx-text-muted)]">Failed</p><p class="mt-2 text-2xl font-bold">{{operations.delivery.failed}}</p><button v-if="operations.delivery.failed" class="mt-2 text-xs font-bold text-[var(--nx-primary)]" :disabled="retryBusy" @click="retryFailed">{{retryBusy?'Queuing…':'Retry all'}}</button></div>
    </section>
    <section>
      <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-[var(--nx-text-muted)]">Service status</h3>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><div v-for="([name,service]) in serviceRows" :key="name" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--nx-border)] px-3 py-3"><span class="truncate text-sm font-semibold capitalize">{{String(name).replaceAll('_',' ')}}</span><NxBadge :tone="statusTone((service as any).status)">{{(service as any).status||'UNKNOWN'}}</NxBadge></div><p v-if="!serviceRows.length" class="text-sm text-[var(--nx-text-muted)]">No collected service data yet.</p></div>
    </section>
    <section class="grid gap-4 xl:grid-cols-2">
      <div class="rounded-xl border border-[var(--nx-border)] p-4"><h3 class="font-bold">HQ delivery</h3><div class="mt-4 grid gap-4 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold md:col-span-2">HQ HTTPS URL<input v-model="operations.settings.monitoring_hq_url" placeholder="https://hq.example.com" :class="inputClass"></label><label class="grid gap-1 text-sm font-semibold">Heartbeat interval<input v-model.number="operations.settings.monitoring_heartbeat_seconds" type="number" min="30" max="3600" :class="inputClass"></label><label class="grid gap-1 text-sm font-semibold">Local retention days<input v-model.number="operations.settings.monitoring_retention_days" type="number" min="1" max="365" :class="inputClass"></label></div><div class="mt-4 flex items-center justify-between gap-3 rounded-lg bg-[var(--nx-surface-muted)] p-3"><div><p class="text-sm font-bold">Deliver to HQ</p><p class="text-xs text-[var(--nx-text-muted)]">Requires an HTTPS endpoint and runtime-only token.</p></div><button type="button" role="switch" :aria-checked="operations.settings.monitoring_hq_enabled" class="relative h-7 w-12 shrink-0 rounded-full transition" :class="operations.settings.monitoring_hq_enabled?'bg-[var(--nx-primary)]':'bg-[var(--nx-border)]'" @click="operations.settings.monitoring_hq_enabled=!operations.settings.monitoring_hq_enabled"><span class="absolute top-1 size-5 rounded-full bg-white shadow transition-all" :class="operations.settings.monitoring_hq_enabled?'left-6':'left-1'"></span></button></div></div>
      <div class="rounded-xl border border-[var(--nx-border)] p-4"><h3 class="font-bold">Health thresholds</h3><div class="mt-4 grid gap-4 md:grid-cols-2"><label class="grid gap-1 text-sm font-semibold">Disk warning %<input v-model.number="operations.settings.monitoring_disk_warning_percent" type="number" min="1" max="99" :class="inputClass"></label><label class="grid gap-1 text-sm font-semibold">Disk critical %<input v-model.number="operations.settings.monitoring_disk_critical_percent" type="number" min="2" max="100" :class="inputClass"></label><label class="grid gap-1 text-sm font-semibold md:col-span-2">Memory warning %<input v-model.number="operations.settings.monitoring_memory_warning_percent" type="number" min="1" max="100" :class="inputClass"></label></div><div class="mt-5 flex justify-end"><NxButton :disabled="settingsBusy" @click="saveMonitoring">{{settingsBusy?'Saving…':'Save Monitoring Settings'}}</NxButton></div></div>
    </section>
    <section class="rounded-xl border border-[var(--nx-border)] p-4"><div class="flex items-center justify-between gap-3"><div><h3 class="font-bold">Incident history</h3><p class="text-xs text-[var(--nx-text-muted)]">Status transitions recorded during the last 24 hours.</p></div><NxBadge tone="neutral">{{operations.history.events.length}} events</NxBadge></div><div class="mt-3 max-h-56 space-y-2 overflow-auto"><div v-for="event in operations.history.events" :key="event.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[var(--nx-surface-muted)] px-3 py-2 text-sm"><span><b>{{event.previous_status||'UNKNOWN'}}</b> → <b>{{event.current_status}}</b></span><span class="text-xs text-[var(--nx-text-muted)]">{{fmt(event.started_at)}} · {{event.recovered_at?'Recovered '+fmt(event.recovered_at):'Active'}}</span></div><p v-if="!operations.history.events.length" class="py-5 text-center text-sm text-[var(--nx-text-muted)]">No status transitions in the last 24 hours.</p></div></section>
  </div>
  <p v-else class="py-8 text-center text-sm text-[var(--nx-text-muted)]">{{operationsBusy?'Loading infrastructure monitoring…':'Monitoring data is unavailable.'}}</p>
</NxCard>
<NxTableShell min-width="1050px">
<thead class="bg-[var(--nx-surface-muted)] text-xs uppercase">
<tr>
<th class="px-4 py-3">Token</th>
<th class="px-4 py-3">Purpose and scopes</th>
<th class="px-4 py-3">Created</th>
<th class="px-4 py-3">Last used</th>
<th class="px-4 py-3">Expires</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3 text-right">Action</th>
</tr>
</thead>
<tbody>
<tr v-if="!rows.length">
<td colspan="7" class="p-12 text-center text-[var(--nx-text-muted)]">No integration tokens have been created.</td>
</tr>
<tr v-for="r in rows" :key="r.id">
<td class="px-4 py-3">
<b>{{r.name}}</b>
<small class="block text-[var(--nx-text-muted)]">#{{r.id}} · {{r.description||'No description'}}</small>
</td>
<td class="px-4 py-3">
<NxBadge tone="neutral">{{r.purpose||'CUSTOM'}}</NxBadge>
<NxBadge class="ml-1" :tone="r.transport_policy==='HTTPS'?'success':r.transport_policy==='HTTP'?'warning':'neutral'">{{r.transport_policy||'BOTH'}}</NxBadge>
<div class="mt-2 flex max-w-md flex-wrap gap-1">
<code v-for="s in r.scopes||[]" :key="s" class="rounded bg-[var(--nx-surface-muted)] px-1.5 py-1 text-xs">{{s}}</code>
</div>
</td>
<td class="whitespace-nowrap px-4 py-3">{{fmt(r.created_at)}}</td>
<td class="whitespace-nowrap px-4 py-3">{{fmt(r.last_used_at)}}<small v-if="r.last_used_ip" class="block font-mono text-[var(--nx-text-muted)]">{{r.last_used_ip}}</small>
</td>
<td class="whitespace-nowrap px-4 py-3">{{fmt(r.expires_at)}}</td>
<td class="px-4 py-3">
<NxBadge :tone="String(r.status).toUpperCase()==='ACTIVE'?'success':'neutral'">{{r.status||'UNKNOWN'}}</NxBadge>
</td>
<td class="px-4 py-3 text-right">
<NxButton v-if="String(r.status).toUpperCase()==='ACTIVE'" variant="danger" @click="askRevoke(r)">Revoke</NxButton>
</td>
</tr>
</tbody>
</NxTableShell>
<NxModal :open="createOpen" title="Create integration token" description="The secret is shown only once. Store it in a secure credential manager." max-width="52rem" @close="createOpen=false">
<div class="grid gap-4 p-6 md:grid-cols-2">
<label class="grid gap-1.5 text-sm font-semibold">Token name<input v-model="form.name" maxlength="100" placeholder="Central monitoring" :class="inputClass">
</label>
<label class="grid gap-1.5 text-sm font-semibold">Purpose<select v-model="form.purpose" :class="inputClass">
<option value="CENTRAL_MONITORING">Central monitoring</option>
<option value="READ_ONLY_MONITORING">Read-only monitoring</option>
<option value="NOC_INTEGRATION">NOC integration</option>
<option value="EXTERNAL_INTEGRATION">External integration</option>
<option value="CUSTOM">Custom</option>
</select>
</label>
<label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Description<input v-model="form.description" maxlength="255" placeholder="Where and why this token is used" :class="inputClass">
</label>
<label class="grid gap-1.5 text-sm font-semibold">Expires at (optional)<input v-model="form.expires_at" type="datetime-local" :class="inputClass">
</label>
<label class="grid gap-1.5 text-sm font-semibold">Allowed transport<select v-model="form.transport_policy" :class="inputClass">
<option value="HTTPS">HTTPS only (recommended)</option>
<option value="HTTP">HTTP only</option>
<option value="BOTH">HTTP and HTTPS</option>
</select><small class="font-normal text-[var(--nx-text-muted)]">HTTP exposes the bearer token to the network. Use it only on an approved private network.</small></label>
<fieldset class="md:col-span-2">
<legend class="text-sm font-bold">Read-only scopes</legend>
<div class="mt-3 grid gap-2 md:grid-cols-2 lg:grid-cols-3">
<label v-for="s in scopes" :key="s" class="flex items-center gap-2 rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-3 text-xs">
<input v-model="form.scopes" type="checkbox" :value="s" class="size-4 accent-[var(--nx-primary)]">
<code>{{s}}</code>
</label>
</div>
</fieldset>
</div>
<template #footer>
<NxButton variant="secondary" @click="createOpen=false">Cancel</NxButton>
<NxButton :disabled="busy" @click="create">{{busy?'Creating…':'Create Token'}}</NxButton>
</template>
</NxModal>
<NxConfirmDialog :open="confirmOpen" title="Revoke API token?" :description="'Integrations using '+(target?.name||'this token')+' will immediately stop authenticating.'" confirm-label="Revoke Token" danger :busy="busy" :error="error" @close="confirmOpen=false" @confirm="revoke"/>
</div>
</template>
<style scoped>:global([data-nx-next-root="api-tokens"]){max-width:100%;overflow-x:hidden}</style>
