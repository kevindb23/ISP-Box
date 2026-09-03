import { getJson, type ApiEnvelope } from '../../lib/api';
export type Row=Record<string,any>;
export const get=<T=any>(url:string)=>getJson<T>(url);
export async function post<T=any>(url:string,payload:unknown={}):Promise<T>{const token=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json',...(token?{'X-CSRF-TOKEN':token}:{})},body:JSON.stringify(payload)});const envelope=await response.json() as ApiEnvelope<T>;if(!response.ok||!envelope.ok)throw new Error(envelope.message||`Request failed (${response.status})`);return envelope.data;}
export const items=(data:any):Row[]=>Array.isArray(data)?data:Array.isArray(data?.items)?data.items:[];
