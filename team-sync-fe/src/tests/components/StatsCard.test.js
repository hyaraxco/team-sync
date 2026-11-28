import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCard from '@/components/common/StatsCard.vue'

describe('StatsCard', () => {
    let wrapper

    beforeEach(() => {
        wrapper = mount(StatsCard, {
            props: {
                title: 'Total Users',
                value: 1234,
                iconName: 'Users',
                subtitle: '+12.5% vs last month',
                subtitleColor: 'text-success'
            }
        })
    })

    afterEach(() => {
        wrapper.unmount()
    })

    it('announces loading instead of showing a misleading metric', async () => {
        await wrapper.setProps({ loading: true });
        expect(wrapper.get('[role="status"]').text()).toBe('Loading Total Users...');
        expect(wrapper.get('[role="status"]').element.closest('[aria-busy="true"]')).toBeNull();
        expect(wrapper.text()).not.toContain('+12.5% vs last month');
        expect(wrapper.find('.tabular-nums').exists()).toBe(false);
        await wrapper.setProps({ loading: false });
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('.tabular-nums').exists()).toBe(true);
    });

    it('renders with baseline card styling', () => {
        const card = wrapper.find('[data-testid="stats-card"]')
        expect(card.exists()).toBe(true)
        expect(card.classes()).not.toContain('bg-white')
        expect(card.attributes('style')).toContain('background: var(--color-surface)')
        expect(card.classes()).toContain('border-brand-border')
        expect(card.classes()).toContain('rounded-2xl')
    })

    it('uses tabular-nums for numeric value', () => {
        const value = wrapper.find('.tabular-nums')
        expect(value.exists()).toBe(true)
    })


    it('renders title and subtitle', () => {
        expect(wrapper.text()).toContain('Total Users')
        expect(wrapper.text()).toContain('+12.5% vs last month')
    })
})
