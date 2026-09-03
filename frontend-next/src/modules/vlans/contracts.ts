export type VlanType = 'C_VLAN' | 'S_VLAN';
export interface VlanSummary { total_vlans: number; total_c_vlans: number; total_s_vlans: number; deployed_count: number; }
export interface VlanRow { id: number; olt_id: number; olt_port_id?: number | null; parent_svlan_id?: number | null; vlan_id: number; vlan_type: VlanType; name: string; description?: string; deployment_status?: string; deployed_at?: string | null; deployment_output?: string | null; olt_ip_address?: string; olt_port_path?: string | null; }
export interface MgmtVlanRow { id: number; olt_id: number; olt_port_id?: number | null; mgmt_vlan: number; description?: string; created_at?: string; olt_ip_address?: string; olt_port_path?: string | null; }
export interface OltOption { id: number; name?: string; ip_address?: string; }
export interface OltPortOption { id: number; olt_id: number; frame?: number; slot?: number; port?: number; port_type?: string; board_type?: string; board_name?: string; board?: string; }
export interface VlanPayload { olt_id: number; olt_port_id?: number | null; parent_svlan_id?: number | null; vlan_id: number; vlan_type: VlanType; name: string; description: string; }
export interface MgmtVlanPayload { id?: number | null; olt_id: number; olt_port_id: number; mgmt_vlan: number; description: string; }
