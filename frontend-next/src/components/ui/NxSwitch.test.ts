// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import NxSwitch from './NxSwitch.vue';

describe('NxSwitch', () => {
  it('shows the current state and emits the next value when toggled', async () => {
    const wrapper = mount(NxSwitch, { props: { modelValue: false, label: 'Telegram notifications' } });

    expect(wrapper.get('[role="switch"]').attributes('aria-checked')).toBe('false');
    expect(wrapper.text()).toContain('OFF');
    expect(wrapper.text()).not.toContain('Disabled');

    await wrapper.get('[role="switch"]').trigger('click');

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true]);
  });

  it('renders the enabled state with accessible labeling', () => {
    const wrapper = mount(NxSwitch, { props: { modelValue: true, label: 'Telegram notifications' } });

    expect(wrapper.get('[role="switch"]').attributes('aria-checked')).toBe('true');
    expect(wrapper.get('[role="switch"]').attributes('aria-label')).toBe('Telegram notifications');
    expect(wrapper.get('[role="switch"]').attributes('style')).toContain('border-radius: 9999px');
    expect(wrapper.text()).toContain('ON');
    expect(wrapper.text()).not.toContain('Enabled');
  });
});
