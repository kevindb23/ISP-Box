export interface RouterProfile { id?: number; config?: Record<string, unknown>; rendered_config?: string; host_key_trusted?: boolean; [key: string]: unknown; }
export interface RouterResponse { core?: RouterProfile; frr?: RouterProfile; core_hash?: string | null; frr_hash?: string | null; core_defaults?: Record<string, unknown>; frr_defaults?: Record<string, unknown>; deployments?: unknown[]; }
export interface RouterRuntime { type?: string; output?: string; error?: string; exit_code?: number; }
