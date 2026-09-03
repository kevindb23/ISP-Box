import { createApp } from 'vue';
import FoundationPreview from './preview/FoundationPreview.vue';
import PlansPage from './modules/plans/PlansPage.vue';
import type { SubscriberPlan } from './modules/plans/contracts';
import SubscribersPage from './modules/subscribers/SubscribersPage.vue';
import type { SubscriberPlanOption, SubscriberRow } from './modules/subscribers/contracts';
import BngPage from './modules/bng/BngPage.vue';
import RoutersPage from './modules/routers/RoutersPage.vue';
import CgnatPage from './modules/cgnat/CgnatPage.vue';
import VlansPage from './modules/vlans/VlansPage.vue';
import RadiusPage from './modules/radius/RadiusPage.vue';
import OltPage from './modules/olt/OltPage.vue';
import NapPage from './modules/nap/NapPage.vue';
import OntPage from './modules/ont/OntPage.vue';
import ProvisioningPage from './modules/provisioning/ProvisioningPage.vue';
import BillingPage from './modules/billing/BillingPage.vue';
import TicketsPage from './modules/tickets/TicketsPage.vue';
import TechniciansPage from './modules/technicians/TechniciansPage.vue';
import AttendancePage from './modules/attendance/AttendancePage.vue';
import WorkOrdersPage from './modules/workorders/WorkOrdersPage.vue';
import UsersPage from './modules/users/UsersPage.vue';
import AuditPage from './modules/audit/AuditPage.vue';
import BrandingPage from './modules/branding/BrandingPage.vue';
import SystemSettingsPage from './modules/system-settings/SystemSettingsPage.vue';
import ApiTokensPage from './modules/api-tokens/ApiTokensPage.vue';
import PaymentGatewayPage from './modules/payment-gateway/PaymentGatewayPage.vue';
import DashboardPage from './modules/dashboard/DashboardPage.vue';
import './styles/theme.css';

function mountNextRoots(scope: ParentNode = document): void {
  scope.querySelectorAll<HTMLElement>('[data-nx-next-root]').forEach((root) => {
    if (root.dataset.nxNextMounted === '1') return;

    if (root.dataset.nxNextRoot === 'foundation-preview') {
      createApp(FoundationPreview).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }


    if (root.dataset.nxNextRoot === 'dashboard') {
      createApp(DashboardPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'plans') {
      const propsNode = root.querySelector<HTMLScriptElement>('script[data-nx-next-props]');
      const props = propsNode?.textContent ? JSON.parse(propsNode.textContent) as { plans?: SubscriberPlan[] } : {};
      createApp(PlansPage, { initialPlans: props.plans ?? [] }).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'subscribers') {
      const propsNode = root.querySelector<HTMLScriptElement>('script[data-nx-next-props]');
      const props = propsNode?.textContent ? JSON.parse(propsNode.textContent) as { subscribers?: SubscriberRow[]; plans?: SubscriberPlanOption[] } : {};
      createApp(SubscribersPage, { initialSubscribers: props.subscribers ?? [], initialPlans: props.plans ?? [] }).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'bng') {
      createApp(BngPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'routers') {
      createApp(RoutersPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'cgnat') {
      createApp(CgnatPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'vlans') {
      createApp(VlansPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'radius') {
      createApp(RadiusPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'olt') {
      createApp(OltPage, { mode: root.dataset.mode || 'devices', initialOltId: Number(root.dataset.oltId || 0), initialProfileTab: root.dataset.profileTab || 'dba' }).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'nap') {
      createApp(NapPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'ont') {
      createApp(OntPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'service-provisioning') {
      createApp(ProvisioningPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'billing') {
      createApp(BillingPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'tickets') {
      createApp(TicketsPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'technicians') {
      createApp(TechniciansPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'attendance') {
      createApp(AttendancePage, { canViewTeam: root.dataset.canViewTeam === '1', role: root.dataset.role || '' }).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'work-orders') {
      createApp(WorkOrdersPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'users') {
      createApp(UsersPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'audit') {
      createApp(AuditPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'branding') {
      createApp(BrandingPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'system-settings') {
      createApp(SystemSettingsPage).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'api-tokens') {
      const propsNode = root.querySelector<HTMLScriptElement>('script[data-nx-next-props]');
      const props = propsNode?.textContent ? JSON.parse(propsNode.textContent) as { tokens?: unknown[]; monitoringIdentity?: Record<string, unknown> } : {};
      createApp(ApiTokensPage, { initialTokens: props.tokens ?? [], initialIdentity: props.monitoringIdentity ?? {} }).mount(root);
      root.dataset.nxNextMounted = '1';
      return;
    }

    if (root.dataset.nxNextRoot === 'payment-gateway') {
      createApp(PaymentGatewayPage).mount(root);
      root.dataset.nxNextMounted = '1';
    }
  });
}

mountNextRoots();

// NexusBox keeps the application shell mounted and replaces only the main
// content during sidebar navigation. ES modules are evaluated once per URL,
// so remount new opt-in roots when the shell announces an AJAX page load.
document.addEventListener('nx:page-load', (event) => {
  const content = (event as CustomEvent<{ content?: ParentNode }>).detail?.content;
  mountNextRoots(content ?? document);
});
