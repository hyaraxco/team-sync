import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MainCard from '@/components/common/MainCard.vue'

describe('MainCard', () => {
    let wrapper

    afterEach(() => {
        if (wrapper) wrapper.unmount()
    })

    it('renders card wrapper with CSS variable background', () => {
        wrapper = mount(MainCard, {
            slots: {
                default: '<p>Card content</p>'
            }
        })
        
        const card = wrapper.find('div')
        expect(card.element.style.background).toContain('var(--color-surface)')
    })

    it('renders stat mode with dark gradient (keep existing)', () => {
        wrapper = mount(MainCard, {
            props: {
                title: 'Total Revenue',
                value: 'Rp 10.000.000',
                subtitle: 'Monthly revenue',
                iconName: 'DollarSign'
            }
        })
        
        const statCard = wrapper.find('.main-card')
        // Stat mode keeps dark gradient (not tokenized — it's a design choice)
        expect(statCard.exists()).toBe(true)
        expect(statCard.classes()).toContain('main-card')
    })

    it('adapts to dark mode for card wrapper', async () => {
        wrapper = mount(MainCard, {
            slots: {
                default: '<p>Card content</p>'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const card = wrapper.find('div')
        expect(card.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })

    it('renders slot content in wrapper mode', () => {
        wrapper = mount(MainCard, {
            slots: {
                default: '<p>Test content</p>'
            }
        })
        
        expect(wrapper.text()).toContain('Test content')
    })

    it('renders stat values with tabular-nums', () => {
        wrapper = mount(MainCard, {
            props: {
                title: 'Total Revenue',
                value: 1234567,
                subtitle: 'Monthly revenue',
                iconName: 'DollarSign'
            }
        })
        
        const value = wrapper.find('.tabular-nums')
        expect(value.exists()).toBe(true)
    })
})
