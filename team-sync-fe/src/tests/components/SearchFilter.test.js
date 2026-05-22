import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchFilter from '@/components/common/SearchFilter.vue'

describe('SearchFilter', () => {
    let wrapper

    afterEach(() => {
        if (wrapper) wrapper.unmount()
    })

    it('renders search input with CSS variable background', () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: '' },
                placeholder: 'Search...'
            }
        })
        
        const container = wrapper.find('[data-testid="search-filter-container"]')
        expect(container.element.style.background).toContain('var(--color-surface)')
    })

    it('emits update:modelValue on input', async () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: '' },
                placeholder: 'Search...'
            }
        })
        
        const input = wrapper.find('input')
        await input.setValue('test query')
        
        // Wait for debounce
        await new Promise(resolve => setTimeout(resolve, 350))
        
        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    })

    it('shows reset button when search value is not empty', async () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: 'test' },
                placeholder: 'Search...'
            }
        })
        
        const resetBtn = wrapper.find('button')
        expect(resetBtn.exists()).toBe(true)
        expect(resetBtn.text()).toContain('Reset')
    })

    it('emits reset event when reset button clicked', async () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: 'test' },
                placeholder: 'Search...'
            }
        })
        
        const resetBtn = wrapper.find('button')
        await resetBtn.trigger('click')
        
        expect(wrapper.emitted('reset')).toBeTruthy()
    })

    it('adapts to dark mode', async () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: '' },
                placeholder: 'Search...'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const container = wrapper.find('[data-testid="search-filter-container"]')
        expect(container.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })

    it('renders filter dropdowns when provided', () => {
        wrapper = mount(SearchFilter, {
            props: {
                modelValue: { search: '', status: '' },
                filters: [
                    {
                        key: 'status',
                        label: 'All Status',
                        icon: 'CheckCircle',
                        options: [
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' }
                        ]
                    }
                ]
            }
        })
        
        const select = wrapper.find('select')
        expect(select.exists()).toBe(true)
    })
})
