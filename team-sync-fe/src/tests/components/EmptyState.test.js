import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from '@/components/common/EmptyState.vue'

describe('EmptyState', () => {
    let wrapper

    afterEach(() => {
        if (wrapper) wrapper.unmount()
    })

    it('renders with default Inbox icon', () => {
        wrapper = mount(EmptyState, {
            props: {
                title: 'No data found',
                subtitle: 'Try adjusting your filters'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders Video icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'Video',
                title: 'No meetings',
                subtitle: 'Schedule your first meeting'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders Bell icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'Bell',
                title: 'No notifications',
                subtitle: 'You are all caught up'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders Layout icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'Layout',
                title: 'No templates',
                subtitle: 'Create your first template'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders Target icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'Target',
                title: 'No goals',
                subtitle: 'Set your first goal'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders BarChart3 icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'BarChart3',
                title: 'No analytics',
                subtitle: 'Data will appear here'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('renders Calendar icon when specified', () => {
        wrapper = mount(EmptyState, {
            props: {
                icon: 'Calendar',
                title: 'No events',
                subtitle: 'Schedule your first event'
            }
        })
        
        const icon = wrapper.find('svg')
        expect(icon.exists()).toBe(true)
    })

    it('uses CSS variables for text colors', () => {
        wrapper = mount(EmptyState, {
            props: {
                title: 'No data',
                subtitle: 'Empty state'
            }
        })
        
        const title = wrapper.find('p')
        const style = title.element.style
        
        // Should use CSS variable, not hardcoded text-gray-*
        expect(style.color).toContain('var(--color-text-')
    })

    it('adapts to dark mode', async () => {
        wrapper = mount(EmptyState, {
            props: {
                title: 'No data',
                subtitle: 'Empty state'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const title = wrapper.find('p')
        const style = title.element.style
        expect(style.color).toContain('var(--color-text-')
        
        document.documentElement.classList.remove('dark')
    })

    it('renders slot content', () => {
        wrapper = mount(EmptyState, {
            props: {
                title: 'No data'
            },
            slots: {
                default: '<button>Add Item</button>'
            }
        })
        
        expect(wrapper.html()).toContain('<button>Add Item</button>')
    })
})
