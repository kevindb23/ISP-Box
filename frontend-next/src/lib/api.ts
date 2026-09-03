export interface ApiEnvelope<T> {
  ok: boolean;
  success: boolean;
  status: 'success' | 'error';
  message: string;
  data: T;
  errors: Record<string, string[]> | string[];
}

export async function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  });

  const envelope = await response.json() as ApiEnvelope<T>;
  if (!response.ok || !envelope.ok) {
    throw new Error(envelope.message || `Request failed (${response.status})`);
  }

  return envelope.data;
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export async function postForm<T>(url: string, form: FormData): Promise<ApiEnvelope<T>> {
  const token = csrfToken();
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    },
    body: form,
  });
  const envelope = await response.json() as ApiEnvelope<T>;
  if (!response.ok || !envelope.ok) {
    throw new Error(envelope.message || `Request failed (${response.status})`);
  }
  return envelope;
}
