import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SystemMaintenancePage from './SystemMaintenancePage.vue';

function response(data: Record<string, unknown>) {
  return new Response(JSON.stringify({ ok: true, success: true, message: 'OK', data }), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('SystemMaintenancePage', () => {
  afterEach(() => vi.restoreAllMocks());

  it('renders immediate active maintenance state', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(response({
      enabled: true,
      message: 'We are upgrading the network.',
      starts_at: null,
      ends_at: null,
      active: true,
    }));

    const wrapper = mount(SystemMaintenancePage);
    await flushPromises();

    expect(wrapper.get('[data-testid="maintenance-status"]').text()).toContain('Active now');
    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('We are upgrading the network.');
  });

  it('renders a scheduled maintenance window as inactive before it starts', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(response({
      enabled: true,
      message: 'Planned upgrade.',
      starts_at: '2030-01-01T01:00',
      ends_at: '2030-01-01T02:00',
      active: false,
    }));

    const wrapper = mount(SystemMaintenancePage);
    await flushPromises();

    expect(wrapper.get('[data-testid="maintenance-status"]').text()).toContain('Scheduled');
    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('Planned upgrade.');
  });

  it('renders an expired maintenance window as inactive', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(response({
      enabled: true,
      message: 'Completed upgrade.',
      starts_at: '2020-01-01T01:00',
      ends_at: '2020-01-01T02:00',
      active: false,
    }));

    const wrapper = mount(SystemMaintenancePage);
    await flushPromises();

    expect(wrapper.get('[data-testid="maintenance-status"]').text()).toContain('Inactive');
  });
});
