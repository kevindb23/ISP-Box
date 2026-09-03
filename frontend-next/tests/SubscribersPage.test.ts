import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import SubscribersPage from '../src/modules/subscribers/SubscribersPage.vue';
import type { SubscriberRow } from '../src/modules/subscribers/contracts';

vi.mock('../src/modules/subscribers/api', () => ({
  createSubscriber: vi.fn(), deleteSubscriber: vi.fn(), getSubscriber: vi.fn(), listSubscriberPlans: vi.fn(),
  listSubscribers: vi.fn(), reactivateSubscriber: vi.fn(), resetPortalPassword: vi.fn(), resetPppPassword: vi.fn(),
  suspendSubscriber: vi.fn(), updateSubscriber: vi.fn(),
}));

const subscriber: SubscriberRow = {
  id: 1, account_number: 'ACC-001', full_name: 'John Doe', contact_number: '09170000000', email: 'john@example.com',
  address: 'Test Address', ppp_username: 'john.ppp', plan_id: 1, plan_name: 'Plan 25M', account_type: 'POSTPAID',
  service_status: 'ACTIVE', service_number: 'SVC-001', next_due_date: '2026-09-01', expires_at: '', nap_name: 'NAP-01',
  nap_splitter_port: '1', ont_serial: 'ONT123', installed_at: '', cvlan: '50', svlan: '3001', online: 1,
  online_text: 'ONLINE', last_seen: null,
};

const wrappers: VueWrapper[] = [];
function render(): VueWrapper {
  const wrapper = mount(SubscribersPage, { props: { initialSubscribers: [subscriber], initialPlans: [{ id: 1, plan_name: 'Plan 25M' }] }, attachTo: document.body });
  wrappers.push(wrapper);
  return wrapper;
}
afterEach(() => { wrappers.splice(0).forEach((wrapper) => wrapper.unmount()); document.body.innerHTML = ''; });

describe('SubscribersPage', () => {
  it('renders registry data and guarded row actions', () => {
    const wrapper = render();
    expect(wrapper.text()).toContain('Add Subscriber');
    expect(wrapper.text()).toContain('John Doe');
    expect(wrapper.find('tbody').text()).toContain('View');
    expect(wrapper.find('tbody').text()).toContain('Edit');
    expect(wrapper.find('tbody').text()).toContain('Suspend');
    expect(wrapper.find('tbody').text()).toContain('Delete');
  });

  it('filters subscriber rows without API mutations', async () => {
    const wrapper = render();
    const search = wrapper.get('input[type="search"]');
    await search.setValue('missing');
    expect(wrapper.text()).toContain('No subscribers found');
    await search.setValue('john.ppp');
    expect(wrapper.find('tbody').text()).toContain('John Doe');
  });

  it('opens create and destructive confirmation dialogs without submitting', async () => {
    const wrapper = render();
    await wrapper.get('button').trigger('click');
    expect(document.body.textContent).toContain('Add Subscriber');
    document.body.querySelector<HTMLButtonElement>('button[aria-label="Close"]')?.click();
    await wrapper.vm.$nextTick();
    const deleteButton = wrapper.findAll('tbody button').find((button) => button.text() === 'Delete');
    await deleteButton?.trigger('click');
    expect(document.body.textContent).toContain('Delete subscriber?');
  });
});
