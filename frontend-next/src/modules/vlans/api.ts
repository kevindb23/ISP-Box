import { getJson, postForm } from '../../lib/api';
import type { MgmtVlanPayload, MgmtVlanRow, OltOption, OltPortOption, VlanPayload, VlanRow, VlanSummary } from './contracts';
const ROOT='/api/v1/vlan-management';
function formOf(values:Record<string,unknown>){const form=new FormData();Object.entries(values).forEach(([key,value])=>{if(value!==null&&value!==undefined)form.set(key,String(value));});return form;}
export const getVlanSummary=()=>getJson<VlanSummary>(`${ROOT}/summary`);
export const listVlans=()=>getJson<VlanRow[]>(`${ROOT}/vlans`);
export const listMgmtVlans=()=>getJson<MgmtVlanRow[]>(`${ROOT}/mgmt-vlans`);
export const listOltOptions=()=>getJson<OltOption[]>('/api/v1/olt-management/devices');
export const listOltPorts=(oltId:number)=>getJson<OltPortOption[]>(`/api/v1/olt-management/ports/by-olt/${oltId}`);
export const createVlan=(payload:VlanPayload)=>postForm<Record<string,unknown>>(`${ROOT}/vlans`,formOf(payload as unknown as Record<string,unknown>));
export const updateVlan=(id:number,payload:VlanPayload)=>postForm<VlanRow>(`${ROOT}/vlans/${id}/update`,formOf(payload as unknown as Record<string,unknown>));
export const retryVlan=(id:number)=>postForm<Record<string,unknown>>(`${ROOT}/vlans/${id}/retry`,new FormData());
export const deleteVlan=(id:number)=>postForm<Record<string,unknown>>(`${ROOT}/vlans/${id}/delete`,new FormData());
export const saveMgmtVlan=(payload:MgmtVlanPayload)=>postForm<Record<string,unknown>>(`${ROOT}/mgmt-vlans`,formOf(payload as unknown as Record<string,unknown>));
export const deleteMgmtVlan=(id:number)=>postForm<Record<string,unknown>>(`${ROOT}/mgmt-vlans/${id}/delete`,new FormData());
