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
                subtitleColor: 'text-success',
                colorScheme: 'blue'
            }
        })
    })

    afterEach(() => {
        wrapper.unmount()
    })

    it('renders with CSS variable background', () => {
        const card = wrapper.find('.stats-card')
        const style = card.element.style
        
        // Should use CSS variable, not hardcoded bg-white class
        expect(style.background).toContain('var(--color-surface)')
    })

    it('adapts to dark mode when .dark class added to html', async () => {
        document.documentElement.classList.add('dark')
        
        await wrapper.vm.$nextTick()
        
        const card = wrapper.find('.stats-card')
        const style = card.element.style
        
        // Should still use same CSS variable (value changes via :root/.dark)
        expect(style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })

    it('uses tabular-nums for numeric value', () => {
        const value = wrapper.find('.tabular-nums')
        expect(value.exists()).toBe(true)
    })

    it('renders icon with correct color scheme', () => {
        const iconWrapper = wrapper.find('.bg-blue-50')
        expect(iconWrapper.exists()).toBe(true)
        
        const icon = wrapper.find('.text-blue-600')
        expect(icon.exists()).toBe(true)
    })

    it('renders title and subtitle', () => {
        expect(wrapper.text()).toContain('Total Users')
        expect(wrapper.text()).toContain('+12.5% vs last month')
    })
})
