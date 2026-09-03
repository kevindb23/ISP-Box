export interface CgnatConfig {
  id?: number;
  enabled: number | boolean;
  inside_network: string;
  bng_interface: string;
  public_start_ip: string;
  public_end_ip: string;
  egress_interface: string;
}

export interface PostroutingRule {
  rule: string;
  hash: string;
  removable: boolean;
}

export interface CgnatRuntime {
  interfaces?: string[];
  nat_rules?: PostroutingRule[];
  warning?: string | null;
}

export interface CgnatResponse {
  config?: Partial<CgnatConfig> | null;
  runtime?: CgnatRuntime;
}
