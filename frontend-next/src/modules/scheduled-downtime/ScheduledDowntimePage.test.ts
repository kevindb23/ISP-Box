import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import ScheduledDowntimePage from './ScheduledDowntimePage.vue';
import { deleteJson, getJson, postForm, putJson } from '../../lib/api';

vi.mock('../../lib/api', () => ({
  getJson: vi.fn(),
  postForm: vi.fn(),
  putJson: vi.fn(),
  deleteJson: vi.fn(),
}));

const getJsonMock = vi.mocked(getJson);
const postFormMock = vi.mocked(postForm);
const wrappers: VueWrapper[] = [];

const rows = [{
  id: 4,
  title: 'Core router maintenance',
  message: 'Subscribers may briefly lose access while we upgrade the core router.',
  starts_at: '2026-09-10 22:00:00',
  ends_at: '2026-09-11 01:00:00',
  enabled: 1,
  created_at: '2026-09-04 09:00:00',
  updated_at: '2026-09-04 09:00:00',
}, {
  id: 5,
  title: 'Planned billing maintenance',
  message: 'Billing access will remain available.',
  starts_at: '2026-09-20 22:00:00',
  ends_at: '2026-09-20 23:00:00',
  enabled: 0,
}];

function renderPage(): VueWrapper {
  getJsonMock.mockResolvedValue({ items: rows } as never);
  const wrapper = mount(ScheduledDowntimePage, { attachTo: document.body });
  wrappers.push(wrapper);
  return wrapper;
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  document.body.innerHTML = '';
  vi.clearAllMocks();
});

describe('ScheduledDowntimePage', () => {
  it('loads enabled downtime records with status badges and row actions', async () => {
    const wrapper = renderPage();
    await flushPromises();

    expect(wrapper.text()).toContain('Scheduled Downtime');
    expect(wrapper.text()).toContain('Core router maintenance');
    expect(wrapper.text()).toContain('ENABLED');
    expect(wrapper.text()).toContain('DISABLED');
    expect(wrapper.text()).toContain('Edit');
    expect(wrapper.text()).toContain('Delete');
  });

  it('uses REST mutation helpers for editing and deleting a window', async () => {
    const wrapper = renderPage();
    await flushPromises();
    vi.mocked(putJson).mockResolvedValue({ ok: true, success: true, data: {}, message: 'Updated' } as never);
    vi.mocked(deleteJson).mockResolvedValue({ ok: true, success: true, data: {}, message: 'Deleted' } as never);
    window.confirm = vi.fn(() => true);

    const buttons = wrapper.findAll('button');
    await buttons.find((button) => button.text() === 'Edit')?.trigger('click');
    const form = document.body.querySelector('form');
    expect(form).not.toBeNull();
    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();
    expect(putJson).toHaveBeenCalledWith('/api/v1/scheduled-downtime/4', expect.any(Object));

    await buttons.find((button) => button.text() === 'Delete')?.trigger('click');
    await flushPromises();
    expect(deleteJson).toHaveBeenCalledWith('/api/v1/scheduled-downtime/4');
  });

  it('rejects an empty customer message and an end time before the start time', async () => {
    const wrapper = renderPage();
    await flushPromises();
    await wrapper.get('button[aria-label="New scheduled downtime"]').trigger('click');

    const form = document.body.querySelector('form');
    expect(form).not.toBeNull();
    const dateInputs = document.body.querySelectorAll<HTMLInputElement>('input[type="datetime-local"]');
    dateInputs[0].value = '2026-09-12T02:00';
    dateInputs[0].dispatchEvent(new Event('input', { bubbles: true }));
    dateInputs[1].value = '2026-09-12T01:00';
    dateInputs[1].dispatchEvent(new Event('input', { bubbles: true }));
    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await wrapper.vm.$nextTick();

    expect(document.body.textContent).toContain('Message is required.');
    expect(document.body.textContent).toContain('End time must be after start time.');
    expect(postFormMock).not.toHaveBeenCalled();
  });
});
