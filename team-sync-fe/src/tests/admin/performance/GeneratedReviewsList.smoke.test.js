import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import GeneratedReviewsList from '@/components/admin/performance/GeneratedReviewsList.vue'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ params: { id: 1 } })
}))

import { ref } from 'vue'

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn()
  })
}))

vi.mock('@/stores/performanceReview', () => ({
  usePerformanceReviewStore: () => ({
    loading: ref(false),
    generateReviews: vi.fn(),
    assignReviewer: vi.fn()
  })
}))

vi.mock('@/stores/staffMember', () => ({
  useStaffMemberStore: () => ({
    staffMembers: ref([]),
    fetchStaffMembers: vi.fn()
  })
}))

describe('GeneratedReviewsList.vue Smoke Test', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders empty state when cycle has no reviews', () => {
    const wrapper = mount(GeneratedReviewsList, {
      global: {
        plugins: [createPinia()],
      },
      props: {
        cycle: { id: 1, status: 'draft', reviews: [] }
      }
    })

    expect(wrapper.text()).toContain('Generated Reviews')
    expect(wrapper.text()).toContain('No reviews generated yet')
    // Generate reviews button is visible because status is draft
    expect(wrapper.text()).toContain('Generate Reviews')
  })

  it('renders reviews table when reviews exist', () => {
    const cycleWithReviews = {
      id: 1,
      status: 'active',
      reviews: [
        {
          id: 10,
          status: 'pending_self',
          staff_member: {
            user: { first_name: 'John', last_name: 'Doe' },
            job_information: { job_title: 'Developer' }
          },
          reviewer: {
            user: { 
              first_name: 'Jane', 
              last_name: 'Smith',
              roles: [{ name: 'manager' }]
            }
          }
        }
      ]
    }

    const wrapper = mount(GeneratedReviewsList, {
      global: {
        plugins: [createPinia()],
      },
      props: { cycle: cycleWithReviews }
    })

    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('Developer')
    expect(wrapper.text()).toContain('Jane Smith')
    expect(wrapper.text()).toContain('manager')
    expect(wrapper.text()).toContain('pending self')
    expect(wrapper.text()).toContain('Assign')
  })

  it('shows unassigned state when reviewer is null', () => {
    const cycleWithNullReviewer = {
      id: 1,
      status: 'active',
      reviews: [
        {
          id: 10,
          status: 'pending_self',
          staff_member: {
            user: { first_name: 'John', last_name: 'Doe' },
            job_information: { job_title: 'Developer' }
          },
          reviewer: null
        }
      ]
    }

    const wrapper = mount(GeneratedReviewsList, {
      global: {
        plugins: [createPinia()],
      },
      props: { cycle: cycleWithNullReviewer }
    })

    expect(wrapper.text()).toContain('Unassigned')
  })
})
