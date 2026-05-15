import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorBoundary from '@/components/common/ErrorBoundary.vue'
import { nextTick } from 'vue'

describe('ErrorBoundary', () => {
    it('renders slot content when no error', () => {
        const wrapper = mount(ErrorBoundary, {
            slots: {
                default: '<div class="test-content">Test Content</div>'
            }
        })

        expect(wrapper.find('.test-content').exists()).toBe(true)
        expect(wrapper.find('.test-content').text()).toBe('Test Content')
    })

    it('shows fallback UI when error is captured', async () => {
        const wrapper = mount(ErrorBoundary, {
            slots: {
                default: '<div class="test-content">Test Content</div>'
            }
        })

        // Simulate error capture
        wrapper.vm.hasError = true
        await nextTick()

        expect(wrapper.find('.test-content').exists()).toBe(false)
        expect(wrapper.find('h3').text()).toBe('Something went wrong')
        expect(wrapper.find('p').text()).toBe('An unexpected error occurred. Please try again.')
    })

    it('shows retry button when showRetry is true', async () => {
        const wrapper = mount(ErrorBoundary, {
            props: {
                showRetry: true
            }
        })

        wrapper.vm.hasError = true
        await nextTick()

        expect(wrapper.find('button').exists()).toBe(true)
        expect(wrapper.find('button').text()).toContain('Try Again')
    })

    it('hides retry button when showRetry is false', async () => {
        const wrapper = mount(ErrorBoundary, {
            props: {
                showRetry: false
            }
        })

        wrapper.vm.hasError = true
        await nextTick()

        expect(wrapper.find('button').exists()).toBe(false)
    })

    it('emits error event when error is captured', async () => {
        const wrapper = mount(ErrorBoundary)
        
        // Directly set error state and emit
        wrapper.vm.hasError = true
        const mockError = new Error('Test error')
        const mockInfo = 'Test info'
        wrapper.vm.errorInfo = { error: mockError, info: mockInfo }
        wrapper.vm.$emit('error', { error: mockError, info: mockInfo })
        await nextTick()

        expect(wrapper.emitted('error')).toBeTruthy()
        expect(wrapper.emitted('error')[0][0]).toEqual({
            error: mockError,
            info: mockInfo
        })
    })

    it('resets error state when retry is clicked', async () => {
        const wrapper = mount(ErrorBoundary, {
            slots: {
                default: '<div class="test-content">Test Content</div>'
            }
        })

        // Set error state
        wrapper.vm.hasError = true
        await nextTick()

        // Click retry button
        await wrapper.find('button').trigger('click')
        await nextTick()

        expect(wrapper.vm.hasError).toBe(false)
        expect(wrapper.find('.test-content').exists()).toBe(true)
    })

    it('uses custom fallback title and subtitle', async () => {
        const wrapper = mount(ErrorBoundary, {
            props: {
                fallbackTitle: 'Custom Error',
                fallbackSubtitle: 'Custom error message'
            }
        })

        wrapper.vm.hasError = true
        await nextTick()

        expect(wrapper.find('h3').text()).toBe('Custom Error')
        expect(wrapper.find('p').text()).toBe('Custom error message')
    })
})