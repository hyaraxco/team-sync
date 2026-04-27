import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AttendancePeriods from '@/views/admin/attendance/AttendancePeriods.vue';
import { createPinia, setActivePinia } from 'pinia';

describe('AttendancePeriods.vue', () => {
    it('renders the header and table', () => {
        setActivePinia(createPinia());
        
        const wrapper = mount(AttendancePeriods, {
            global: {
                stubs: {
                    RouterLink: true,
                    Icon: true,
                }
            }
        });

        expect(wrapper.text()).toContain('Attendance Periods');
        expect(wrapper.text()).toContain('Readiness');
    });
});
