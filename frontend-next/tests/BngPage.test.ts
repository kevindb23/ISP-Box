import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import BngPage from '../src/modules/bng/BngPage.vue';

vi.mock('../src/modules/bng/api', () => ({
  getBngSetting: vi.fn().mockResolvedValue({ id: 1, enabled: 1, host: '10.0.10.147', port: 22, username: 'root', bng_parent_interface: 'ens17', preferred_interface: 'ens16', host_key_trusted: true }),
  getBngRuntime: vi.fn().mockResolvedValue({ svlan_groups: [{ interface: 'ens17.3001', parent_interface: 'ens17', svlan: 3001, status: 'UP', clients: [] }], client_vlan_interfaces: [{ interface: 'ens17.3001.50', parent_interface: 'ens17', svlan: 3001, cvlan: 50, status: 'UP' }], bng_interfaces: [{ interface: '1WAN0', local_address: '100.64.0.1', peer_address: '100.64.0.2/32', ppp_username: 'user1', subscriber_name: 'John Doe', service_id: 1 }] }),
  getAccelConfig: vi.fn().mockResolvedValue({ profile: { id: 1, status: 'DRAFT', config: {} }, defaults: { modules: ['radius','log_file','pppoe','auth_pap','ippool'], thread_count: 2 }, integration_warnings: [] }),
  activateAccelConfig: vi.fn(), deleteBngSetting: vi.fn(), installBootRecovery: vi.fn(), previewAccelConfig: vi.fn(),
  saveAccelConfig: vi.fn(), saveBngSetting: vi.fn(), scanBngHostKey: vi.fn(), stageAccelConfig: vi.fn(),
  testBngConnection: vi.fn(), trustBngHostKey: vi.fn(),
}));

const wrappers: VueWrapper[] = [];
async function render(): Promise<VueWrapper> { const wrapper=mount(BngPage,{attachTo:document.body});wrappers.push(wrapper);await flushPromises();return wrapper; }
afterEach(()=>{wrappers.splice(0).forEach(wrapper=>wrapper.unmount());document.body.innerHTML='';});

describe('BngPage',()=>{
  it('renders the configured BNG without exposing its password',async()=>{const wrapper=await render();expect(wrapper.text()).toContain('10.0.10.147:22');expect(wrapper.text()).toContain('ens17');expect(wrapper.text()).not.toContain('N3t3ng777');});
  it('renders all runtime tabs',async()=>{const wrapper=await render();const tabs=wrapper.findAll('[role="tablist"] button');await tabs[2].trigger('click');expect(wrapper.text()).toContain('ens17.3001');await tabs[3].trigger('click');expect(wrapper.text()).toContain('ens17.3001.50');await tabs[4].trigger('click');expect(wrapper.text()).toContain('1WAN0');expect(wrapper.text()).toContain('John Doe');});
  it('guards deletion and maintenance activation',async()=>{const wrapper=await render();await wrapper.findAll('button').find(button=>button.text()==='Delete')?.trigger('click');expect(document.body.textContent).toContain('Delete BNG connection?');document.body.querySelectorAll('button').forEach(button=>{if(button.textContent?.trim()==='Cancel')button.click();});await flushPromises();const tabs=wrapper.findAll('[role="tablist"] button');await tabs[1].trigger('click');expect(wrapper.text()).toContain('Save Draft');expect(wrapper.text()).toContain('Activate Maintenance');});
});
