import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import PlansPage from '../src/modules/plans/PlansPage.vue';
import type { SubscriberPlan } from '../src/modules/plans/contracts';

vi.mock('../src/modules/plans/api', () => ({
  createSubscriberPlan: vi.fn(),
  deleteSubscriberPlan: vi.fn(),
  listSubscriberPlans: vi.fn(),
  updateSubscriberPlan: vi.fn(),
}));

const plans: SubscriberPlan[] = [
  {
    id: 1,
    plan_name: 'Plan 25M',
    price: 999,
    description: 'Residential postpaid',
    plan_type: 'POSTPAID',
    validity_days: 30,
    speed_down: 25,
    speed_up: 25,
    speed_mbps: 25,
    is_active: 1,
  },
  {
    id: 2,
    plan_name: 'Prepaid 10M',
    price: 299,
    description: 'Seven-day access',
    plan_type: 'PREPAID',
    validity_days: 7,
    speed_down: 10,
    speed_up: 10,
    speed_mbps: 10,
    is_active: 0,
  },
];

const wrappers: VueWrapper[] = [];

function renderPlans(): VueWrapper {
  const wrapper = mount(PlansPage, {
    props: { initialPlans: plans },
    attachTo: document.body,
  });
  wrappers.push(wrapper);
  return wrapper;
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
  document.body.innerHTML = '';
});

describe('PlansPage', () => {
  it('renders the primary action and permanent row actions', () => {
    const wrapper = renderPlans();

    expect(wrapper.text()).toContain('New Plan');
    expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    expect(wrapper.find('tbody tr').text()).toContain('View');
    expect(wrapper.find('tbody tr').text()).toContain('Edit');
    expect(wrapper.find('tbody tr').text()).toContain('Delete');
  });

  it('filters by search, type, and status', async () => {
    const wrapper = renderPlans();
    const search = wrapper.get('input[type="search"]');
    const selects = wrapper.findAll('section select');

    await search.setValue('prepaid');
    expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    expect(wrapper.find('tbody').text()).toContain('Prepaid 10M');

    await search.setValue('');
    await selects[0].setValue('POSTPAID');
    await selects[1].setValue('ACTIVE');
    expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    expect(wrapper.find('tbody').text()).toContain('Plan 25M');
  });

  it('opens the create, view, edit, and delete overlays', async () => {
    const wrapper = renderPlans();

    await wrapper.get('button').trigger('click');
    expect(document.body.textContent).toContain('Add Subscriber Plan');
    document.body.querySelector<HTMLButtonElement>('button[aria-label="Close"]')?.click();
    await wrapper.vm.$nextTick();

    const firstRowButtons = wrapper.findAll('tbody tr')[0].findAll('button');
    await firstRowButtons[0].trigger('click');
    expect(document.body.textContent).toContain('Plan details');
    document.body.querySelector<HTMLButtonElement>('button[aria-label="Close"]')?.click();
    await wrapper.vm.$nextTick();

    await firstRowButtons[1].trigger('click');
    expect(document.body.textContent).toContain('Edit Subscriber Plan');
    document.body.querySelector<HTMLButtonElement>('button[aria-label="Close"]')?.click();
    await wrapper.vm.$nextTick();

    await firstRowButtons[2].trigger('click');
    expect(document.body.textContent).toContain('Delete plan?');
  });

  it('shows client validation inside the active form dialog', async () => {
    const wrapper = renderPlans();
    await wrapper.get('button').trigger('click');

    const form = document.body.querySelector('form');
    expect(form).not.toBeNull();
    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await wrapper.vm.$nextTick();

    expect(form?.textContent).toContain('Plan name is required.');
  });
});
