<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import NxAlert from '../../components/ui/NxAlert.vue';
import NxButton from '../../components/ui/NxButton.vue';
import NxCard from '../../components/ui/NxCard.vue';
import NxPageHeader from '../../components/ui/NxPageHeader.vue';
import { getJson, postForm } from '../../lib/api';

type Branding = { company_name?:string; logo_text?:string; portal_title?:string; company_address?:string; support_email?:string; support_phone?:string; tin?:string; website?:string; logo_path?:string };
const blank=():Branding=>({company_name:'',logo_text:'',company_address:'',support_email:'',support_phone:'',tin:'',website:'',logo_path:''});
const form=ref<Branding>(blank()),original=ref<Branding>(blank()),loading=ref(false),saving=ref(false),error=ref(''),notice=ref(''),file=ref<File|null>(null),localLogo=ref(''),removeLogo=ref(false);
const inputClass='min-h-11 w-full rounded-md border border-[var(--nx-border)] bg-[var(--nx-surface)] px-3 text-sm text-[var(--nx-text)] outline-none transition focus:border-[var(--nx-primary)]';
const logo=computed(()=>localLogo.value||(!removeLogo.value?String(form.value.logo_path||''):''));
const displayName=computed(()=>String(form.value.logo_text||form.value.portal_title||form.value.company_name||'Your ISP').trim());
const dirty=computed(()=>JSON.stringify(form.value)!==JSON.stringify(original.value)||!!file.value||removeLogo.value);

async function load(show=false){loading.value=true;error.value='';notice.value='';try{const data=await getJson<Branding>('/api/v1/branding');form.value={...blank(),...data,logo_text:data.logo_text??data.portal_title??''};original.value={...form.value};clearLogoStage();if(show)notice.value='Branding settings refreshed.';}catch(e){error.value=e instanceof Error?e.message:'Unable to load branding.';}finally{loading.value=false;}}
function clearLogoStage(){if(localLogo.value.startsWith('blob:'))URL.revokeObjectURL(localLogo.value);file.value=null;localLogo.value='';removeLogo.value=false;}
function chooseLogo(event:Event){error.value='';const picked=(event.target as HTMLInputElement).files?.[0]||null;if(!picked)return;if(!['image/png','image/jpeg','image/webp'].includes(picked.type)){error.value='Logo must be a PNG, JPG, or WEBP image.';(event.target as HTMLInputElement).value='';return;}if(picked.size>2*1024*1024){error.value='Logo must not exceed 2 MB.';(event.target as HTMLInputElement).value='';return;}clearLogoStage();file.value=picked;localLogo.value=URL.createObjectURL(picked);}
function stageRemove(){clearLogoStage();removeLogo.value=true;}
function reset(){form.value={...original.value};clearLogoStage();notice.value='Unsaved changes discarded.';}
function updateShell(data:Branding){const name=String(data.company_name||'').trim()||'ISP-In-A-BOX',url=String(data.logo_path||'').trim();document.querySelectorAll<HTMLElement>('[data-brand-company-name]').forEach(e=>{e.textContent=name;e.title=name;});document.querySelectorAll<HTMLElement>('[data-brand-logo]').forEach(e=>{const next=url?document.createElement('img'):document.createElement('i');if(next instanceof HTMLImageElement){next.src=url;next.alt=name+' logo';next.className='sidebar-brand-logo';}else next.className='bi bi-hdd-network';next.dataset.brandLogo='';e.replaceWith(next);});document.title=name+' · Operations console';window.dispatchEvent(new CustomEvent('nexusbox:branding-updated',{detail:{...data,company_name:name,logo_path:url}}));}
async function save(){saving.value=true;error.value='';notice.value='';try{const body=new FormData();Object.entries(form.value).forEach(([k,v])=>body.append(k,String(v??'')));body.set('logo_path',String(form.value.logo_path||''));body.set('remove_logo',removeLogo.value?'1':'0');if(file.value)body.set('company_logo',file.value);const response=await postForm<Branding>('/api/v1/branding',body);form.value={...blank(),...response.data,logo_text:response.data.logo_text??response.data.portal_title??''};original.value={...form.value};clearLogoStage();updateShell(response.data);notice.value=response.message||'Branding saved successfully.';}catch(e){error.value=e instanceof Error?e.message:'Unable to save branding.';}finally{saving.value=false;}}
onMounted(()=>void load());
</script>

<template>
  <div class="space-y-4 overflow-x-hidden pb-8 text-[var(--nx-text)]">
    <NxPageHeader eyebrow="Company identity" title="Branding" description="Manage the identity shown across the operations console, subscriber portal, invoices, receipts, and login page.">
      <template #actions><NxButton variant="secondary" :disabled="loading||saving" @click="load(true)">{{loading?'Refreshing…':'Refresh'}}</NxButton><NxButton :disabled="saving||loading||!dirty" @click="save">{{saving?'Saving…':'Save Branding'}}</NxButton></template>
    </NxPageHeader>
    <NxAlert v-if="error||notice" :tone="error?'danger':'success'">{{error||notice}}</NxAlert>
    <div v-if="dirty" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-400/40 bg-amber-400/10 px-4 py-3 text-sm"><span><b>Unsaved changes</b> — preview updates locally until you save.</span><NxButton variant="secondary" @click="reset">Discard changes</NxButton></div>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,.8fr)]">
      <NxCard><div class="mb-5"><p class="text-base font-bold">Company information</p><p class="text-sm text-[var(--nx-text-muted)]">Contact and legal details used in customer-facing documents.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Company Name<input v-model="form.company_name" :class="inputClass" autocomplete="organization"></label>
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Logo Text<input v-model="form.logo_text" maxlength="60" placeholder="Example: 1WAN" :class="inputClass"><small class="font-normal text-[var(--nx-text-muted)]">Displayed beside the logo on the login page.</small></label>
          <label class="grid gap-1.5 text-sm font-semibold md:col-span-2">Company Address<textarea v-model="form.company_address" rows="3" :class="inputClass+' py-3'"></textarea></label>
          <label class="grid gap-1.5 text-sm font-semibold">Support Email<input v-model="form.support_email" type="email" :class="inputClass" autocomplete="email"></label>
          <label class="grid gap-1.5 text-sm font-semibold">Support Phone<input v-model="form.support_phone" :class="inputClass" autocomplete="tel"></label>
          <label class="grid gap-1.5 text-sm font-semibold">TIN<input v-model="form.tin" :class="inputClass"></label>
          <label class="grid gap-1.5 text-sm font-semibold">Website<input v-model="form.website" type="url" placeholder="https://example.com" :class="inputClass"></label>
        </div>
      </NxCard>
      <div class="space-y-4">
        <NxCard><p class="text-base font-bold">Brand preview</p><p class="mt-1 text-sm text-[var(--nx-text-muted)]">A compact preview of your login identity.</p><div class="mt-5 flex min-h-40 items-center justify-center rounded-xl border border-[var(--nx-border)] bg-[var(--nx-surface-muted)] p-6"><div class="text-center"><img v-if="logo" :src="logo" alt="Logo preview" class="mx-auto max-h-20 max-w-56 object-contain"><div v-else class="mx-auto grid size-16 place-items-center rounded-2xl bg-[var(--nx-primary)] text-2xl font-black text-white">N</div><p class="mt-4 text-xl font-bold">{{displayName}}</p><p class="mt-1 text-xs text-[var(--nx-text-muted)]">Powered by 1WAN</p></div></div></NxCard>
        <NxCard><p class="text-base font-bold">Company logo</p><p class="mt-1 text-sm text-[var(--nx-text-muted)]">PNG, JPG, or WEBP up to 2 MB. Recommended 300 × 100 px.</p><label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[var(--nx-border)] bg-[var(--nx-surface-muted)] px-5 py-8 text-center transition hover:border-[var(--nx-primary)]"><span class="font-bold">Choose a logo</span><span class="mt-1 text-xs text-[var(--nx-text-muted)]">{{file?.name||'Browse from this device'}}</span><input class="sr-only" type="file" accept="image/png,image/jpeg,image/webp" @change="chooseLogo"></label><NxButton v-if="logo" class="mt-3 w-full" variant="danger" @click="stageRemove">Remove Logo</NxButton></NxCard>
      </div>
    </div>
  </div>
</template>
<style scoped>:global([data-nx-next-root="branding"]){max-width:100%;overflow-x:hidden}</style>
