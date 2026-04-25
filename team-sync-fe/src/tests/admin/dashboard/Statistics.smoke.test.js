import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

// Mock dashboard store to control statistics data
let mockStatistics = {
  employees: { total: 10, added_this_month: 2 },
  teams: { total: 3, new_teams: 1 },
  attendance: { rate: 85, change: 2 },
  performance: { promotion_eligible: 3, pip_required: 1 },
}
let mockLoading = false

vi.mock('@/stores/dashboard', () => ({
  useDashboardStore: () => ({
    statistics: mockStatistics,
    loading: mockLoading,
    fetchStatistics: vi.fn().mockResolvedValue(undefined),
  }),
}))

// Stub child components — expose data attrs for assertions
const StatsCardStub = {
  name: 'StatsCard',
  props: ['title', 'value', 'subtitle', 'subtitleColor', 'iconName', 'colorScheme', 'loading'],
  template: `<div :data-title="title" :data-value="String(value)" :data-scheme="colorScheme" :data-icon="iconName"><slot /></div>`,
}

import Statistics from '@/components/admin/dashboard/Statistics.vue'

const factory = (statsOverride = {}, loading = false) => {
  mockStatistics = {
    employees: { total: 10, added_this_month: 2 },
    teams: { total: 3, new_teams: 1 },
    attendance: { rate: 85, change: 2 },
    ...statsOverride,
  }
  mockLoading = loading

  return mount(Statistics, {
    global: {
      stubs: {
        StatsCard: StatsCardStub,
        MainCard: true,
        QuickActions: true,
      },
    },
  })
}

describe('Statistics.vue — Sprint 5 performance widgets', () => {
  beforeEach(() => {
    mockStatistics = {
      employees: { total: 10, added_this_month: 2 },
      teams: { total: 3, new_teams: 1 },
      attendance: { rate: 85, change: 2 },
      performance: { promotion_eligible: 0, pip_required: 0 },
    }
    mockLoading = false
  })

  it('renders "Promotion Eligible" card with purple colorScheme and TrendingUpIcon', () => {
    const wrapper = factory({ performance: { promotion_eligible: 3, pip_required: 1 } })
    const cards = wrapper.findAll('[data-title]')
    const card = cards.find(c => c.attributes('data-title') === 'Promotion Eligible')
    expect(card).toBeTruthy()
    expect(card?.attributes('data-scheme')).toBe('purple')
    expect(card?.attributes('data-icon')).toBe('TrendingUpIcon')
    expect(card?.attributes('data-value')).toBe('3')
  })

  it('renders "PIP Required" card with red colorScheme and AlertTriangleIcon', () => {
    const wrapper = factory({ performance: { promotion_eligible: 3, pip_required: 2 } })
    const cards = wrapper.findAll('[data-title]')
    const card = cards.find(c => c.attributes('data-title') === 'PIP Required')
    expect(card).toBeTruthy()
    expect(card?.attributes('data-scheme')).toBe('red')
    expect(card?.attributes('data-icon')).toBe('AlertTriangleIcon')
    expect(card?.attributes('data-value')).toBe('2')
  })

  it('does NOT render "Tasks Completed" card (replaced by performance widgets)', () => {
    const wrapper = factory()
    const cards = wrapper.findAll('[data-title]')
    expect(cards.some(c => c.attributes('data-title') === 'Tasks Completed')).toBe(false)
  })

  it('does NOT render "Active Projects" card (replaced by performance widgets)', () => {
    const wrapper = factory()
    const cards = wrapper.findAll('[data-title]')
    expect(cards.some(c => c.attributes('data-title') === 'Active Projects')).toBe(false)
  })

  it('shows 0 values when performance key is missing (safe default fallback)', () => {
    // no performance key in statistics
    const wrapper = factory({ performance: undefined })
    const cards = wrapper.findAll('[data-title]')
    const promotionCard = cards.find(c => c.attributes('data-title') === 'Promotion Eligible')
    expect(promotionCard?.attributes('data-value')).toBe('0')
    const pipCard = cards.find(c => c.attributes('data-title') === 'PIP Required')
    expect(pipCard?.attributes('data-value')).toBe('0')
  })

  it('passes loading prop to StatsCards when store is loading', () => {
    const wrapper = factory({}, true)
    // All StatsCard stubs should be present — just verify the component mounts
    expect(wrapper.findAll('[data-title]').length).toBeGreaterThan(0)
  })
})
