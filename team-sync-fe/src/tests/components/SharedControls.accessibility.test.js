import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { h, reactive, nextTick } from 'vue'
import Input from '@/components/common/form/Input.vue'
import Select from '@/components/common/form/Select.vue'
import TextArea from '@/components/common/form/TextArea.vue'
import SearchFilter from '@/components/common/SearchFilter.vue'
import Pagination from '@/components/common/Pagination.vue'
import TeamPagination from '@/components/admin/team/Pagination.vue'
import PayrollPagination from '@/components/admin/payroll/Pagination.vue'

const fields = [[Input, 'input'], [Select, 'select'], [TextArea, 'textarea']]

function expectAssociatedLabels(wrapper) {
    const controls = wrapper.findAll('input, select, textarea')
    const ids = controls.map(control => control.attributes('id'))
    expect(ids.every(Boolean)).toBe(true)
    expect(new Set(ids).size).toBe(ids.length)
    for (const control of controls) {
        const label = wrapper.findAll('label').find(item => item.attributes('for') === control.attributes('id'))
        expect(label?.text()).toBeTruthy()
    }
}

describe('shared field consumer contracts', () => {
    it.each(fields)('associates repeated labels without deriving IDs from names', (Component) => {
        const wrapper = mount({ render: () => h('form', [
            h(Component, { label: 'Name', name: 'name', error: 'Required' }),
            h(Component, { label: 'Name', name: 'name', error: 'Required' }),
        ]) })
        expectAssociatedLabels(wrapper)
        const errors = wrapper.findAll('[role="alert"]').map(error => error.attributes('id'))
        expect(new Set(errors).size).toBe(2)
        wrapper.unmount()
    })

    it.each(fields)('preserves explicit IDs, layout, native attributes and descriptions', async (Component, tag) => {
        const onFocus = vi.fn()
        const wrapper = mount(Component, {
            props: { label: 'Name', id: 'explicit-name', error: 'Required' },
            attrs: { class: 'col-span-2', style: 'margin-top: 8px', disabled: true, 'aria-describedby': 'name-hint', onFocus },
        })
        const control = wrapper.get(tag)
        expect(control.attributes('id')).toBe('explicit-name')
        expect(wrapper.get('label').attributes('for')).toBe('explicit-name')
        expect(wrapper.classes()).toContain('col-span-2')
        expect(wrapper.element.style.marginTop).toBe('8px')
        expect(control.classes()).not.toContain('col-span-2')
        expect(control.element.disabled).toBe(true)
        expect(wrapper.attributes('disabled')).toBeUndefined()
        expect(control.attributes('aria-describedby')).toBe('name-hint explicit-name-error')
        await wrapper.setProps({ error: '' })
        expect(control.attributes('aria-describedby')).toBe('name-hint')
        await wrapper.setProps({ disabled: false })
        expect(control.element.disabled).toBe(false)
        await control.trigger('focus')
        expect(onFocus).toHaveBeenCalledOnce()
        wrapper.unmount()
    })
})

describe('shared search and pagination accessibility', () => {
    it('associates visible search and filter labels across repeated instances', () => {
        const props = {
            filters: [{ key: 'status', label: 'All Status', options: [{ value: 'active', label: 'Active' }] }],
            showSearchButton: true,
            modelValue: { search: 'staff' },
        }
        const wrapper = mount({ render: () => h('form', [h(SearchFilter, props), h(SearchFilter, props)]) })
        expectAssociatedLabels(wrapper)
        for (const button of wrapper.findAll('button')) expect(button.attributes('type')).toBe('button')
        wrapper.unmount()
    })

    it.each([Pagination, TeamPagination, PayrollPagination])('names pagination controls and retains events', async (Component) => {
        const props = reactive({ meta: { current_page: 2, last_page: 3, per_page: 10, total: 30 }, loading: false })
        const wrapper = mount({ render: () => h('form', [h(Component, props), h(Component, props)]) })
        expectAssociatedLabels(wrapper)
        const pagination = wrapper.findComponent(Component)
        expect(pagination.get('[aria-current="page"]').text()).toBe('2')
        expect(pagination.findAll('[aria-current="page"]')).toHaveLength(1)
        await pagination.get('[aria-label="Previous page"]').trigger('click')
        expect(pagination.emitted('page-change')).toEqual([[1]])
        await pagination.get('[aria-label="Next page"]').trigger('click')
        expect(pagination.emitted('page-change')[1]).toEqual([3])
        for (const button of wrapper.findAll('button')) expect(button.attributes('type')).toBe('button')
        props.loading = true
        await nextTick()
        expect(pagination.get('select').element.disabled).toBe(true)
        expect(pagination.findAll('button').every(button => button.element.disabled)).toBe(true)
        wrapper.unmount()
    })
})
