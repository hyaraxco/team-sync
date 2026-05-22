import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import NotificationPanel from '@/components/admin/NotificationPanel.vue'

const router = createRouter({
    history: createMemoryHistory(),
    routes: [
        { path: '/admin/notifications', name: 'admin.notifications' }
    ]
})

describe('NotificationPanel', () => {
    let wrapper

    afterEach(() => {
        if (wrapper) wrapper.unmount()
    })

    it('renders latest 5 notifications', () => {
        const notifications = Array.from({ length: 10 }, (_, i) => ({
            id: i + 1,
            title: `Notification ${i + 1}`,
            message: `Message ${i + 1}`,
            category: 'info',
            read_at: null,
            created_at: new Date().toISOString()
        }))
        
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications 
            },
            global: { plugins: [router] }
        })
        
        const items = wrapper.findAll('.notification-item')
        expect(items.length).toBe(5)
    })

    it('renders "See all notifications" link', () => {
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications: [] 
            },
            global: { plugins: [router] }
        })
        
        const link = wrapper.find('[data-testid="see-all-link"]')
        expect(link.exists()).toBe(true)
        expect(link.text()).toContain('See all notifications')
    })

    it('"See all" link routes to /admin/notifications', async () => {
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications: [] 
            },
            global: { plugins: [router] }
        })
        
        const link = wrapper.find('[data-testid="see-all-link"]')
        await link.trigger('click')
        
        // Wait for router navigation
        await router.isReady()
        
        expect(router.currentRoute.value.path).toBe('/admin/notifications')
    })

    it('uses CSS variables for panel background', () => {
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications: [] 
            },
            global: { plugins: [router] }
        })
        
        const panel = wrapper.find('.notification-panel')
        const style = panel.element.style
        
        // Should use CSS variable, not hardcoded bg-white
        expect(style.background).toContain('var(--color-surface)')
    })

    it('adapts to dark mode', async () => {
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications: [] 
            },
            global: { plugins: [router] }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const panel = wrapper.find('.notification-panel')
        const style = panel.element.style
        expect(style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })

    it('renders empty state when no notifications', () => {
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications: [] 
            },
            global: { plugins: [router] }
        })
        
        expect(wrapper.text()).toContain('No notifications')
    })

    it('emits select event when notification clicked', async () => {
        const notifications = [{
            id: 1,
            title: 'Test Notification',
            message: 'Test Message',
            category: 'info',
            read_at: null,
            created_at: new Date().toISOString()
        }]
        
        wrapper = mount(NotificationPanel, {
            props: { 
                open: true,
                notifications 
            },
            global: { plugins: [router] }
        })
        
        // Find the button inside the notification item
        const button = wrapper.find('[data-testid="notification-select-1"]')
        await button.trigger('click')
        
        expect(wrapper.emitted('select')).toBeTruthy()
        expect(wrapper.emitted('select')[0]).toEqual([notifications[0]])
    })
})
