import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import Header from '@/components/admin/Header.vue'

// Mock stores
vi.mock('@/stores/auth', () => ({
    useAuthStore: () => ({
        user: { name: 'Test User', email: 'test@example.com' },
        logout: vi.fn()
    })
}))

vi.mock('@/stores/notifications', () => ({
    useNotificationStore: () => ({
        notifications: [],
        loading: false,
        error: null,
        unreadCount: 0,
        fetchNotifications: vi.fn(),
        fetchUnreadCount: vi.fn()
    })
}))

const createTestRouter = (routeName) => {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/admin/analytics', name: 'admin.analytics' },
            { path: '/admin/meetings', name: 'admin.meetings' },
            { path: '/admin/settings', name: 'admin.settings' },
            { path: '/admin/attendance/settings', name: 'admin.attendance.settings' },
            { path: '/admin/attendance/overtime', name: 'admin.attendance.overtime' },
            { path: '/admin/performance/cycles', name: 'admin.performance.cycles' },
            { path: '/admin/performance/my-goals', name: 'admin.performance.my-goals' }
        ]
    })
}

describe('Header', () => {
    it('shows "Analytics" title for analytics route', async () => {
        const router = createTestRouter('admin.analytics')
        await router.push('/admin/analytics')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Analytics')
    })

    it('shows "Meetings" title for meetings route', async () => {
        const router = createTestRouter('admin.meetings')
        await router.push('/admin/meetings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Meetings')
    })

    it('shows "Settings" title for settings route', async () => {
        const router = createTestRouter('admin.settings')
        await router.push('/admin/settings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Settings')
    })

    it('shows "Attendance Settings" for attendance settings route', async () => {
        const router = createTestRouter('admin.attendance.settings')
        await router.push('/admin/attendance/settings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Attendance Settings')
    })

    it('shows "Overtime Management" for overtime route', async () => {
        const router = createTestRouter('admin.attendance.overtime')
        await router.push('/admin/attendance/overtime')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Overtime Management')
    })

    it('shows "Review Cycles" for performance cycles route', async () => {
        const router = createTestRouter('admin.performance.cycles')
        await router.push('/admin/performance/cycles')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('Review Cycles')
    })

    it('shows "My Goals" for my-goals route', async () => {
        const router = createTestRouter('admin.performance.my-goals')
        await router.push('/admin/performance/my-goals')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true, NotificationPanel: true }
            }
        })
        
        expect(wrapper.text()).toContain('My Goals')
    })
})
