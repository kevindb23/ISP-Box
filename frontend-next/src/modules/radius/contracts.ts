export interface RadiusSetting { id: number; host: string; db_name: string; db_user: string; is_active: boolean | number; created_at?: string; updated_at?: string; }
export interface RadiusPayload { host: string; db_name: string; db_user: string; db_password: string; is_active: number; }
