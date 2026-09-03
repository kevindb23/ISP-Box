import { getJson, postForm } from '../../lib/api';
import type { RouterResponse, RouterRuntime } from './contracts';
const ENDPOINT='/api/v1/routers';
function formOf(values:Record<string,unknown>):FormData{const form=new FormData();Object.entries(values).forEach(([key,value])=>form.set(key,String(value??'')));return form;}
export const getRouters=()=>getJson<RouterResponse>(ENDPOINT);
export const saveCoreRouter=(values:Record<string,unknown>)=>postForm<Record<string,unknown>>(`${ENDPOINT}/core`,formOf(values));
export const saveFrrRouter=(values:Record<string,unknown>)=>postForm<Record<string,unknown>>(`${ENDPOINT}/frr`,formOf(values));
export const scanCoreHostKey=()=>getJson<{fingerprint:string}>(`${ENDPOINT}/core/host-key`);
export const trustCoreHostKey=(fingerprint:string)=>postForm<Record<string,unknown>>(`${ENDPOINT}/core/host-key/trust`,formOf({fingerprint}));
export const getCoreRuntime=()=>getJson<RouterRuntime>(`${ENDPOINT}/core/runtime`);
export const getFrrRuntime=()=>getJson<RouterRuntime>(`${ENDPOINT}/frr/runtime`);
export const applyCoreRouter=(hash:string)=>postForm<Record<string,unknown>>(`${ENDPOINT}/core/apply`,formOf({confirmation:'APPLY CORE ROUTER CONFIG',config_hash:hash}));
export const applyFrrRouter=(hash:string)=>postForm<Record<string,unknown>>(`${ENDPOINT}/frr/apply`,formOf({confirmation:'APPLY FRR ROUTER CONFIG',config_hash:hash}));
