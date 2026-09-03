import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import CgnatPage from '../src/modules/cgnat/CgnatPage.vue';
import { applyCgnat, removePostroutingRules, saveCgnat } from '../src/modules/cgnat/api';

vi.mock('../src/modules/cgnat/api', () => ({
  getCgnat: vi.fn().mockResolvedValue({
    config: { id: 1, enabled: 1, inside_network: '100.64.0.0/24', bng_interface: 'ens17', egress_interface: 'ens16', public_start_ip: '126.209.31.170', public_end_ip: '126.209.31.174' },
    runtime: { interfaces: ['ens16', 'ens17', 'ens18'], nat_rules: [{ rule: '-P POSTROUTING ACCEPT', hash: 'policy', removable: false }, { rule: '-A POSTROUTING -s 100.64.0.0/24 -o ens16 -j SNAT --to-source 126.209.31.170-126.209.31.174', hash: 'snat', removable: true }] },
  }),
  saveCgnat: vi.fn(), applyCgnat: vi.fn(), removePostroutingRules: vi.fn(),
}));

const wrappers: VueWrapper[] = [];
async function render() { const wrapper = mount(CgnatPage, { attachTo: document.body }); wrappers.push(wrapper); await flushPromises(); return wrapper; }
afterEach(() => { wrappers.splice(0).forEach((wrapper) => wrapper.unmount()); document.body.innerHTML = ''; vi.clearAllMocks(); });

describe('CgnatPage', () => {
  it('loads desired and runtime state without mutating either', async () => { const wrapper = await render(); expect(wrapper.text()).toContain('100.64.0.0/24'); expect(wrapper.text()).toContain('126.209.31.170'); expect(saveCgnat).not.toHaveBeenCalled(); expect(applyCgnat).not.toHaveBeenCalled(); });
  it('marks edits dirty and prevents applying an unsaved draft', async () => { const wrapper = await render(); const input = wrapper.find('input[placeholder="100.64.0.0/24"]'); await input.setValue('100.64.1.0/24'); expect(wrapper.text()).toContain('Unsaved changes'); const apply = wrapper.findAll('button').find((button) => button.text() === 'Apply CGNAT State'); expect(apply?.attributes('disabled')).toBeDefined(); });
  it('selects only removable live rules and guards removal', async () => { const wrapper = await render(); await wrapper.findAll('[role="tablist"] button')[1].trigger('click'); expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(1); await wrapper.find('input[type="checkbox"]').setValue(true); await wrapper.findAll('button').find((button) => button.text().startsWith('Remove Selected'))?.trigger('click'); expect(document.body.textContent).toContain('Remove selected live rules?'); expect(removePostroutingRules).not.toHaveBeenCalled(); });
});
