# HR Admin Redesign — Plan 1: Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate shared components to CSS-variable dark mode + complete Header title map + audit/add missing CSS tokens

**Architecture:** Foundation layer that unblocks all domain-specific redesign plans. Shared components (`StatsCard`, `MainCard`, `EmptyState`, `SearchFilter`, `NotificationPanel`) get full token migration. Header title map extended for 30+ missing HR routes. CSS variables audited/added to `input.css`.

**Tech Stack:** Vue 3 Composition API, Tailwind CSS v3, CSS custom properties, Lucide icons, Vitest

---

## File Structure

**Shared Components (migrate to tokens):**
- `team-sync-fe/src/components/common/StatsCard.vue` — KPI metric cards
- `team-sync-fe/src/components/common/MainCard.vue` — card wrapper + hero stat mode
- `team-sync-fe/src/components/common/EmptyState.vue` — empty state component + add missing icons
- `team-sync-fe/src/components/common/SearchFilter.vue` — search/filter bar
- `team-sync-fe/src/components/admin/NotificationPanel.vue` — header dropdown + add "See all" link

**Header:**
- `team-sync-fe/src/components/admin/Header.vue` — extend `pageTitles` computed for 30+ routes

**CSS Tokens:**
- `team-sync-fe/src/assets/css/input.css` — add missing tokens if needed

**Tests:**
- `team-sync-fe/src/tests/components/StatsCard.test.js` — dark mode toggle test
- `team-sync-fe/src/tests/components/MainCard.test.js` — dark mode toggle test
- `team-sync-fe/src/tests/components/EmptyState.test.js` — new icons + dark mode test
- `team-sync-fe/src/tests/components/SearchFilter.test.js` — dark mode toggle test
- `team-sync-fe/src/tests/components/NotificationPanel.test.js` — "See all" link + dark mode test
- `team-sync-fe/src/tests/components/Header.test.js` — new route titles test

---

### Task 1: Audit CSS Tokens in input.css

**Files:**
- Read: `team-sync-fe/src/assets/css/input.css:10-86`

- [ ] **Step 1: Read existing tokens**

Run:
```bash
cd team-sync-fe
cat src/assets/css/input.css | grep -A 40 ":root"
```

Expected: See `:root` and `.dark` token definitions lines 10-86.

- [ ] **Step 2: Verify required tokens exist**

Check for these tokens in both `:root` and `.dark`:
- `--color-surface` (replaces `bg-white`)
- `--color-text-primary` (replaces `text-gray-900`)
- `--color-text-secondary` (replaces `text-gray-500`)
- `--color-border-default` (replaces `border-gray-200`)
- `--main-bg` (page background)

Expected: All tokens present per lines 10-86. No additions needed.

- [ ] **Step 3: Document token mapping**

Create reference comment in plan:
```
Token mapping for migration:
- bg-white → background: var(--color-surface)
- bg-gray-50 → background: var(--color-surface-raised)
- bg-gray-100 → background: var(--color-surface-overlay)
- text-gray-900 → color: var(--color-text-primary)
- text-gray-500 → color: var(--color-text-secondary)
- text-gray-400 → color: var(--color-text-muted)
- border-gray-200 → border-color: var(--color-border-default)
```

- [ ] **Step 4: Commit audit notes**

```bash
git add docs/plans/on_going/2026-05-22-hr-redesign-plan-1-foundation.md
git commit -m "docs: add CSS token audit for HR redesign foundation"
```

---

### Task 2: Migrate StatsCard.vue to CSS Tokens

**Files:**
- Modify: `team-sync-fe/src/components/common/StatsCard.vue`
- Test: `team-sync-fe/src/tests/components/StatsCard.test.js`

- [ ] **Step 1: Write failing dark mode test**

Create `team-sync-fe/src/tests/components/StatsCard.test.js`:

```javascript
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCard from '@/components/common/StatsCard.vue'

describe('StatsCard', () => {
    let wrapper

    beforeEach(() => {
        wrapper = mount(StatsCard, {
            props: {
                title: 'Total Users',
                value: 1234,
                icon: 'Users',
                trend: 12.5,
                trendLabel: 'vs last month'
            }
        })
    })

    it('renders with light mode tokens by default', () => {
        const card = wrapper.find('[data-testid="stats-card"]')
        const computedStyle = window.getComputedStyle(card.element)
        
        // Should use CSS variable, not hardcoded white
        expect(card.element.style.background).toContain('var(--color-surface)')
    })

    it('adapts to dark mode when .dark class added to html', async () => {
        document.documentElement.classList.add('dark')
        
        await wrapper.vm.$nextTick()
        
        const card = wrapper.find('[data-testid="stats-card"]')
        const computedStyle = window.getComputedStyle(card.element)
        
        // Should still use same CSS variable (value changes via :root/.dark)
        expect(card.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })

    it('uses tabular-nums for numeric value', () => {
        const value = wrapper.find('[data-testid="stats-value"]')
        expect(value.classes()).toContain('tabular-nums')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/StatsCard.test.js
```

Expected: FAIL — `data-testid="stats-card"` not found, or `bg-white` hardcoded instead of CSS variable.

- [ ] **Step 3: Read current StatsCard.vue implementation**

Run:
```bash
cd team-sync-fe
cat src/components/common/StatsCard.vue
```

Expected: See template with hardcoded `bg-white`, `text-gray-*`, `border-gray-*` classes.

- [ ] **Step 4: Migrate StatsCard.vue to CSS tokens**

Replace hardcoded classes in `team-sync-fe/src/components/common/StatsCard.vue`:

```vue
<template>
    <div 
        data-testid="stats-card"
        class="rounded-2xl border p-6 transition-all duration-200 hover:shadow-md"
        :style="{
            background: 'var(--color-surface)',
            borderColor: 'var(--color-border-default)'
        }"
    >
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div 
                    class="flex h-12 w-12 items-center justify-center rounded-xl"
                    :class="`bg-${iconColor}-50`"
                >
                    <component 
                        :is="iconComponent" 
                        :class="`text-${iconColor}-600`"
                        :size="24"
                    />
                </div>
                <div>
                    <p 
                        class="text-sm font-medium"
                        :style="{ color: 'var(--color-text-secondary)' }"
                    >
                        {{ title }}
                    </p>
                    <p 
                        data-testid="stats-value"
                        class="text-2xl font-bold tabular-nums"
                        :style="{ color: 'var(--color-text-primary)' }"
                    >
                        {{ formattedValue }}
                    </p>
                </div>
            </div>
            <div v-if="trend !== null" class="text-right">
                <div class="flex items-center space-x-1">
                    <component 
                        :is="trendIcon" 
                        :size="16"
                        :class="trendColor"
                    />
                    <span 
                        class="text-sm font-medium tabular-nums"
                        :class="trendColor"
                    >
                        {{ Math.abs(trend) }}%
                    </span>
                </div>
                <p 
                    class="text-xs"
                    :style="{ color: 'var(--color-text-muted)' }"
                >
                    {{ trendLabel }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { TrendingUp, TrendingDown } from 'lucide-vue-next'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [Number, String],
        required: true
    },
    icon: {
        type: String,
        required: true
    },
    iconColor: {
        type: String,
        default: 'blue'
    },
    trend: {
        type: Number,
        default: null
    },
    trendLabel: {
        type: String,
        default: ''
    },
    formatter: {
        type: Function,
        default: null
    }
})

const iconComponent = computed(() => {
    // Dynamic icon import from lucide-vue-next
    const icons = import.meta.glob('lucide-vue-next', { eager: true })
    return icons[props.icon] || null
})

const formattedValue = computed(() => {
    if (props.formatter) {
        return props.formatter(props.value)
    }
    if (typeof props.value === 'number') {
        return props.value.toLocaleString()
    }
    return props.value
})

const trendIcon = computed(() => {
    return props.trend >= 0 ? TrendingUp : TrendingDown
})

const trendColor = computed(() => {
    return props.trend >= 0 ? 'text-success-600' : 'text-danger-600'
})
</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/StatsCard.test.js
```

Expected: PASS — all 3 tests green.

- [ ] **Step 6: Manual dark mode verification**

Run:
```bash
cd team-sync-fe
bun run dev
```

Open browser → http://localhost:5173/admin/dashboard
Toggle dark mode via header switch.
Verify stats cards adapt (no white flash, text readable).

- [ ] **Step 7: Commit**

```bash
cd team-sync-fe
git add src/components/common/StatsCard.vue src/tests/components/StatsCard.test.js
git commit -m "feat: migrate StatsCard to CSS variable dark mode"
```


---

### Task 3: Migrate MainCard.vue to CSS Tokens

**Files:**
- Modify: `team-sync-fe/src/components/common/MainCard.vue`
- Test: `team-sync-fe/src/tests/components/MainCard.test.js`

- [ ] **Step 1: Write failing dark mode test**

Create `team-sync-fe/src/tests/components/MainCard.test.js`:

```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MainCard from '@/components/common/MainCard.vue'

describe('MainCard', () => {
    it('renders card wrapper with CSS variable background', () => {
        const wrapper = mount(MainCard, {
            slots: {
                default: '<p>Card content</p>'
            }
        })
        
        const card = wrapper.find('[data-testid="main-card"]')
        expect(card.element.style.background).toContain('var(--color-surface)')
    })

    it('renders stat mode with dark gradient (keep existing)', () => {
        const wrapper = mount(MainCard, {
            props: {
                statMode: true,
                statTitle: 'Total Revenue',
                statValue: 'Rp 10.000.000',
                statIcon: 'DollarSign'
            }
        })
        
        const statCard = wrapper.find('[data-testid="main-card-stat"]')
        // Stat mode keeps dark gradient (not tokenized — it's a design choice)
        expect(statCard.classes()).toContain('main-card')
    })

    it('adapts to dark mode for card wrapper', async () => {
        const wrapper = mount(MainCard, {
            slots: {
                default: '<p>Card content</p>'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const card = wrapper.find('[data-testid="main-card"]')
        expect(card.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/MainCard.test.js
```

Expected: FAIL — `data-testid` not found or hardcoded `bg-white`.

- [ ] **Step 3: Read current MainCard.vue**

Run:
```bash
cd team-sync-fe
cat src/components/common/MainCard.vue
```

Expected: See template with `bg-white` for card wrapper, `.main-card` class for stat mode.

- [ ] **Step 4: Migrate MainCard.vue to CSS tokens**

Replace in `team-sync-fe/src/components/common/MainCard.vue`:

```vue
<template>
    <div v-if="statMode" data-testid="main-card-stat" class="main-card rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-white/80">{{ statTitle }}</p>
                <p class="text-3xl font-bold text-white tabular-nums">{{ statValue }}</p>
            </div>
            <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-white/10">
                <component :is="statIconComponent" :size="32" class="text-white" />
            </div>
        </div>
    </div>
    <div 
        v-else 
        data-testid="main-card"
        class="rounded-2xl border p-6 shadow-sm transition-shadow duration-200 hover:shadow-md"
        :style="{
            background: 'var(--color-surface)',
            borderColor: 'var(--color-border-default)'
        }"
    >
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    statMode: {
        type: Boolean,
        default: false
    },
    statTitle: {
        type: String,
        default: ''
    },
    statValue: {
        type: String,
        default: ''
    },
    statIcon: {
        type: String,
        default: ''
    }
})

const statIconComponent = computed(() => {
    if (!props.statIcon) return null
    const icons = import.meta.glob('lucide-vue-next', { eager: true })
    return icons[props.statIcon] || null
})
</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/MainCard.test.js
```

Expected: PASS — all 3 tests green.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/components/common/MainCard.vue src/tests/components/MainCard.test.js
git commit -m "feat: migrate MainCard wrapper to CSS variable dark mode"
```

---

### Task 4: Migrate EmptyState.vue + Add Missing Icons

**Files:**
- Modify: `team-sync-fe/src/components/common/EmptyState.vue`
- Test: `team-sync-fe/src/tests/components/EmptyState.test.js`

- [ ] **Step 1: Write failing test for new icons + dark mode**

Create `team-sync-fe/src/tests/components/EmptyState.test.js`:

```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from '@/components/common/EmptyState.vue'

describe('EmptyState', () => {
    it('renders with default Inbox icon', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No data found',
                description: 'Try adjusting your filters'
            }
        })
        
        const icon = wrapper.find('[data-testid="empty-icon"]')
        expect(icon.exists()).toBe(true)
    })

    it('renders Video icon when specified', () => {
        const wrapper = mount(EmptyState, {
            props: {
                icon: 'Video',
                title: 'No meetings',
                description: 'Schedule your first meeting'
            }
        })
        
        const icon = wrapper.find('[data-testid="empty-icon"]')
        expect(icon.exists()).toBe(true)
    })

    it('renders Bell icon when specified', () => {
        const wrapper = mount(EmptyState, {
            props: {
                icon: 'Bell',
                title: 'No notifications',
                description: 'You are all caught up'
            }
        })
        
        const icon = wrapper.find('[data-testid="empty-icon"]')
        expect(icon.exists()).toBe(true)
    })

    it('renders Layout icon when specified', () => {
        const wrapper = mount(EmptyState, {
            props: {
                icon: 'Layout',
                title: 'No templates',
                description: 'Create your first template'
            }
        })
        
        const icon = wrapper.find('[data-testid="empty-icon"]')
        expect(icon.exists()).toBe(true)
    })

    it('uses CSS variables for text colors', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No data',
                description: 'Empty state'
            }
        })
        
        const title = wrapper.find('[data-testid="empty-title"]')
        const desc = wrapper.find('[data-testid="empty-description"]')
        
        expect(title.element.style.color).toContain('var(--color-text-primary)')
        expect(desc.element.style.color).toContain('var(--color-text-secondary)')
    })

    it('adapts to dark mode', async () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No data',
                description: 'Empty state'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const title = wrapper.find('[data-testid="empty-title"]')
        expect(title.element.style.color).toContain('var(--color-text-primary)')
        
        document.documentElement.classList.remove('dark')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/EmptyState.test.js
```

Expected: FAIL — `Video`, `Bell`, `Layout` icons not in iconMap, or hardcoded `text-gray-*`.

- [ ] **Step 3: Read current EmptyState.vue**

Run:
```bash
cd team-sync-fe
cat src/components/common/EmptyState.vue
```

Expected: See iconMap missing `Video`, `Bell`, `Layout`, `Target`, `BarChart3`, `Calendar`. Hardcoded `text-gray-*` classes.

- [ ] **Step 4: Migrate EmptyState.vue + add missing icons**

Replace in `team-sync-fe/src/components/common/EmptyState.vue`:

```vue
<template>
    <div class="flex flex-col items-center justify-center py-12 space-y-4">
        <div 
            data-testid="empty-icon"
            class="flex h-16 w-16 items-center justify-center rounded-full opacity-50"
            :style="{ background: 'var(--color-surface-overlay)' }"
        >
            <component 
                :is="iconComponent" 
                :size="32"
                :style="{ color: 'var(--color-text-secondary)' }"
            />
        </div>
        <div class="text-center space-y-2">
            <h3 
                data-testid="empty-title"
                class="text-xl font-semibold"
                :style="{ color: 'var(--color-text-primary)' }"
            >
                {{ title }}
            </h3>
            <p 
                data-testid="empty-description"
                class="text-sm max-w-md"
                :style="{ color: 'var(--color-text-secondary)' }"
            >
                {{ description }}
            </p>
        </div>
        <slot name="action" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { 
    Inbox, 
    Users, 
    FileText, 
    Calendar, 
    Video, 
    Bell, 
    Layout, 
    Target, 
    BarChart3 
} from 'lucide-vue-next'

const props = defineProps({
    icon: {
        type: String,
        default: 'Inbox'
    },
    title: {
        type: String,
        default: 'No data found'
    },
    description: {
        type: String,
        default: 'Try adjusting your filters or create a new item'
    }
})

const iconMap = {
    Inbox,
    Users,
    FileText,
    Calendar,
    Video,
    Bell,
    Layout,
    Target,
    BarChart3
}

const iconComponent = computed(() => {
    return iconMap[props.icon] || Inbox
})
</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/EmptyState.test.js
```

Expected: PASS — all 6 tests green.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/components/common/EmptyState.vue src/tests/components/EmptyState.test.js
git commit -m "feat: add missing icons to EmptyState + migrate to CSS tokens"
```


---

### Task 5: Migrate SearchFilter.vue to CSS Tokens

**Files:**
- Modify: `team-sync-fe/src/components/common/SearchFilter.vue`
- Test: `team-sync-fe/src/tests/components/SearchFilter.test.js`

- [ ] **Step 1: Write failing dark mode test**

Create `team-sync-fe/src/tests/components/SearchFilter.test.js`:

```javascript
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchFilter from '@/components/common/SearchFilter.vue'

describe('SearchFilter', () => {
    it('renders search input with CSS variable background', () => {
        const wrapper = mount(SearchFilter, {
            props: {
                modelValue: '',
                placeholder: 'Search...'
            }
        })
        
        const input = wrapper.find('[data-testid="search-input"]')
        expect(input.element.style.background).toContain('var(--color-surface)')
    })

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(SearchFilter, {
            props: {
                modelValue: '',
                placeholder: 'Search...'
            }
        })
        
        const input = wrapper.find('input')
        await input.setValue('test query')
        
        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['test query'])
    })

    it('shows clear button when value is not empty', async () => {
        const wrapper = mount(SearchFilter, {
            props: {
                modelValue: 'test',
                placeholder: 'Search...'
            }
        })
        
        const clearBtn = wrapper.find('[data-testid="clear-button"]')
        expect(clearBtn.exists()).toBe(true)
    })

    it('emits clear event when clear button clicked', async () => {
        const wrapper = mount(SearchFilter, {
            props: {
                modelValue: 'test',
                placeholder: 'Search...'
            }
        })
        
        const clearBtn = wrapper.find('[data-testid="clear-button"]')
        await clearBtn.trigger('click')
        
        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([''])
    })

    it('adapts to dark mode', async () => {
        const wrapper = mount(SearchFilter, {
            props: {
                modelValue: '',
                placeholder: 'Search...'
            }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const input = wrapper.find('[data-testid="search-input"]')
        expect(input.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/SearchFilter.test.js
```

Expected: FAIL — `data-testid` not found or hardcoded `bg-white`.

- [ ] **Step 3: Read current SearchFilter.vue**

Run:
```bash
cd team-sync-fe
cat src/components/common/SearchFilter.vue
```

Expected: See hardcoded `bg-white`, `text-gray-*`, `border-gray-*`.

- [ ] **Step 4: Migrate SearchFilter.vue to CSS tokens**

Replace in `team-sync-fe/src/components/common/SearchFilter.vue`:

```vue
<template>
    <div 
        class="flex items-center space-x-2 rounded-2xl border px-4 py-3 transition-all duration-200 focus-within:ring-2 focus-within:ring-brand-primary/20"
        :style="{
            background: 'var(--color-surface)',
            borderColor: 'var(--color-border-default)'
        }"
    >
        <Search 
            :size="20" 
            :style="{ color: 'var(--color-text-muted)' }"
        />
        <input
            data-testid="search-input"
            v-model="localValue"
            type="text"
            :placeholder="placeholder"
            class="flex-1 border-0 bg-transparent outline-none"
            :style="{ 
                color: 'var(--color-text-primary)',
                '::placeholder': { color: 'var(--color-text-muted)' }
            }"
            @input="handleInput"
        />
        <button
            v-if="localValue"
            data-testid="clear-button"
            type="button"
            class="rounded-lg p-1 transition-colors hover:bg-gray-100"
            :style="{ 
                background: 'transparent',
                ':hover': { background: 'var(--color-surface-overlay)' }
            }"
            @click="handleClear"
        >
            <X 
                :size="16" 
                :style="{ color: 'var(--color-text-muted)' }"
            />
        </button>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Search, X } from 'lucide-vue-next'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Search...'
    },
    debounce: {
        type: Number,
        default: 300
    }
})

const emit = defineEmits(['update:modelValue'])

const localValue = ref(props.modelValue)
let debounceTimer = null

watch(() => props.modelValue, (newVal) => {
    localValue.value = newVal
})

const handleInput = () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', localValue.value)
    }, props.debounce)
}

const handleClear = () => {
    localValue.value = ''
    emit('update:modelValue', '')
}
</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/SearchFilter.test.js
```

Expected: PASS — all 5 tests green.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/components/common/SearchFilter.vue src/tests/components/SearchFilter.test.js
git commit -m "feat: migrate SearchFilter to CSS variable dark mode"
```

---

### Task 6: Migrate NotificationPanel + Add "See All" Link

**Files:**
- Modify: `team-sync-fe/src/components/admin/NotificationPanel.vue`
- Test: `team-sync-fe/src/tests/components/NotificationPanel.test.js`

- [ ] **Step 1: Write failing test for "See all" link + dark mode**

Create `team-sync-fe/src/tests/components/NotificationPanel.test.js`:

```javascript
import { describe, it, expect } from 'vitest'
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
    it('renders latest 5 notifications', () => {
        const notifications = Array.from({ length: 10 }, (_, i) => ({
            id: i + 1,
            title: `Notification ${i + 1}`,
            message: `Message ${i + 1}`,
            category: 'info',
            read_at: null,
            created_at: new Date().toISOString()
        }))
        
        const wrapper = mount(NotificationPanel, {
            props: { notifications },
            global: { plugins: [router] }
        })
        
        const items = wrapper.findAll('[data-testid="notification-item"]')
        expect(items.length).toBe(5)
    })

    it('renders "See all notifications" link', () => {
        const wrapper = mount(NotificationPanel, {
            props: { notifications: [] },
            global: { plugins: [router] }
        })
        
        const link = wrapper.find('[data-testid="see-all-link"]')
        expect(link.exists()).toBe(true)
        expect(link.text()).toBe('See all notifications')
    })

    it('"See all" link routes to /admin/notifications', async () => {
        const wrapper = mount(NotificationPanel, {
            props: { notifications: [] },
            global: { plugins: [router] }
        })
        
        const link = wrapper.find('[data-testid="see-all-link"]')
        await link.trigger('click')
        
        expect(router.currentRoute.value.path).toBe('/admin/notifications')
    })

    it('uses CSS variables for panel background', () => {
        const wrapper = mount(NotificationPanel, {
            props: { notifications: [] },
            global: { plugins: [router] }
        })
        
        const panel = wrapper.find('[data-testid="notification-panel"]')
        expect(panel.element.style.background).toContain('var(--color-surface)')
    })

    it('adapts to dark mode', async () => {
        const wrapper = mount(NotificationPanel, {
            props: { notifications: [] },
            global: { plugins: [router] }
        })
        
        document.documentElement.classList.add('dark')
        await wrapper.vm.$nextTick()
        
        const panel = wrapper.find('[data-testid="notification-panel"]')
        expect(panel.element.style.background).toContain('var(--color-surface)')
        
        document.documentElement.classList.remove('dark')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/NotificationPanel.test.js
```

Expected: FAIL — `data-testid="see-all-link"` not found, or hardcoded light-only scoped CSS.

- [ ] **Step 3: Read current NotificationPanel.vue**

Run:
```bash
cd team-sync-fe
cat src/components/admin/NotificationPanel.vue
```

Expected: See hardcoded light gradient in scoped CSS, no "See all" link, hardcoded `bg-white`/`text-gray-*`.

- [ ] **Step 4: Migrate NotificationPanel.vue + add "See all" link**

Replace in `team-sync-fe/src/components/admin/NotificationPanel.vue`:

```vue
<template>
    <div 
        data-testid="notification-panel"
        class="absolute right-0 top-full mt-2 w-96 rounded-2xl border shadow-lg"
        :style="{
            background: 'var(--color-surface)',
            borderColor: 'var(--color-border-default)'
        }"
    >
        <div 
            class="border-b px-4 py-3"
            :style="{ borderColor: 'var(--color-border-default)' }"
        >
            <div class="flex items-center justify-between">
                <h3 
                    class="text-lg font-semibold"
                    :style="{ color: 'var(--color-text-primary)' }"
                >
                    Notifications
                </h3>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-sm font-medium text-brand-primary hover:text-brand-primary-dark"
                    @click="$emit('mark-all-read')"
                >
                    Mark all read
                </button>
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <div v-if="visibleNotifications.length === 0" class="px-4 py-8 text-center">
                <Bell 
                    :size="48" 
                    class="mx-auto mb-2 opacity-50"
                    :style="{ color: 'var(--color-text-muted)' }"
                />
                <p 
                    class="text-sm"
                    :style="{ color: 'var(--color-text-secondary)' }"
                >
                    No notifications
                </p>
            </div>

            <div
                v-for="notification in visibleNotifications"
                :key="notification.id"
                data-testid="notification-item"
                class="border-b px-4 py-3 transition-colors cursor-pointer"
                :class="{ 'opacity-60': notification.read_at }"
                :style="{ 
                    borderColor: 'var(--color-border-default)',
                    ':hover': { background: 'var(--color-surface-overlay)' }
                }"
                @click="$emit('select', notification)"
            >
                <div class="flex items-start space-x-3">
                    <div 
                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg"
                        :class="getCategoryBgClass(notification.category)"
                    >
                        <component 
                            :is="getCategoryIcon(notification.category)" 
                            :size="20"
                            :class="getCategoryTextClass(notification.category)"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p 
                            class="text-sm font-medium"
                            :style="{ color: 'var(--color-text-primary)' }"
                        >
                            {{ notification.title }}
                        </p>
                        <p 
                            class="text-xs mt-1"
                            :style="{ color: 'var(--color-text-secondary)' }"
                        >
                            {{ notification.message }}
                        </p>
                        <p 
                            class="text-xs mt-1"
                            :style="{ color: 'var(--color-text-muted)' }"
                        >
                            {{ formatTime(notification.created_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div 
            class="border-t px-4 py-3 text-center"
            :style="{ borderColor: 'var(--color-border-default)' }"
        >
            <router-link
                data-testid="see-all-link"
                to="/admin/notifications"
                class="text-sm font-medium text-brand-primary hover:text-brand-primary-dark"
            >
                See all notifications →
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { Bell, Info, AlertCircle, CheckCircle, AlertTriangle } from 'lucide-vue-next'
import { DateTime } from 'luxon'

const props = defineProps({
    notifications: {
        type: Array,
        default: () => []
    }
})

defineEmits(['select', 'mark-all-read'])

const visibleNotifications = computed(() => {
    return props.notifications.slice(0, 5)
})

const unreadCount = computed(() => {
    return props.notifications.filter(n => !n.read_at).length
})

const getCategoryIcon = (category) => {
    const iconMap = {
        info: Info,
        warning: AlertTriangle,
        success: CheckCircle,
        error: AlertCircle
    }
    return iconMap[category] || Info
}

const getCategoryBgClass = (category) => {
    const bgMap = {
        info: 'bg-blue-50',
        warning: 'bg-amber-50',
        success: 'bg-success-50',
        error: 'bg-danger-50'
    }
    return bgMap[category] || 'bg-blue-50'
}

const getCategoryTextClass = (category) => {
    const textMap = {
        info: 'text-blue-600',
        warning: 'text-amber-600',
        success: 'text-success-600',
        error: 'text-danger-600'
    }
    return textMap[category] || 'text-blue-600'
}

const formatTime = (timestamp) => {
    return DateTime.fromISO(timestamp).toRelative()
}
</script>
```

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/NotificationPanel.test.js
```

Expected: PASS — all 5 tests green.

- [ ] **Step 6: Manual verification**

Run:
```bash
cd team-sync-fe
bun run dev
```

Open browser → http://localhost:5173/admin/dashboard
Click bell icon in header.
Verify:
- Panel uses dark mode tokens (no white flash when toggling dark mode)
- "See all notifications →" link appears at bottom
- Link routes to `/admin/notifications`

- [ ] **Step 7: Commit**

```bash
cd team-sync-fe
git add src/components/admin/NotificationPanel.vue src/tests/components/NotificationPanel.test.js
git commit -m "feat: add 'See all' link to NotificationPanel + migrate to CSS tokens"
```


---

### Task 7: Extend Header.vue Title Map for HR Routes

**Files:**
- Modify: `team-sync-fe/src/components/admin/Header.vue`
- Test: `team-sync-fe/src/tests/components/Header.test.js`

- [ ] **Step 1: Write failing test for new route titles**

Create `team-sync-fe/src/tests/components/Header.test.js`:

```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import Header from '@/components/admin/Header.vue'

const createMockRouter = (currentRouteName) => {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/admin/analytics', name: 'admin.analytics' },
            { path: '/admin/meetings', name: 'admin.meetings' },
            { path: '/admin/settings', name: 'admin.settings' },
            { path: '/admin/notifications', name: 'admin.notifications' },
            { path: '/admin/attendance/settings', name: 'admin.attendance.settings' },
            { path: '/admin/attendance/overtime', name: 'admin.attendance.overtime' },
            { path: '/admin/performance/cycles', name: 'admin.performance.cycles' },
            { path: '/admin/performance/my-goals', name: 'admin.performance.my-goals' }
        ]
    })
}

describe('Header', () => {
    it('shows "Analytics" title for analytics route', async () => {
        const router = createMockRouter('admin.analytics')
        await router.push('/admin/analytics')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Analytics')
    })

    it('shows "Meetings" title for meetings route', async () => {
        const router = createMockRouter('admin.meetings')
        await router.push('/admin/meetings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Meetings')
    })

    it('shows "Settings" title for settings route', async () => {
        const router = createMockRouter('admin.settings')
        await router.push('/admin/settings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Settings')
    })

    it('shows "Notifications" title for notifications route', async () => {
        const router = createMockRouter('admin.notifications')
        await router.push('/admin/notifications')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Notifications')
    })

    it('shows "Attendance Settings" for attendance settings route', async () => {
        const router = createMockRouter('admin.attendance.settings')
        await router.push('/admin/attendance/settings')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Attendance Settings')
    })

    it('shows "Overtime Management" for overtime route', async () => {
        const router = createMockRouter('admin.attendance.overtime')
        await router.push('/admin/attendance/overtime')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Overtime Management')
    })

    it('shows "Review Cycles" for performance cycles route', async () => {
        const router = createMockRouter('admin.performance.cycles')
        await router.push('/admin/performance/cycles')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('Review Cycles')
    })

    it('shows "My Goals" for my-goals route', async () => {
        const router = createMockRouter('admin.performance.my-goals')
        await router.push('/admin/performance/my-goals')
        
        const wrapper = mount(Header, {
            global: { 
                plugins: [router],
                stubs: { RouterLink: true }
            }
        })
        
        const title = wrapper.find('[data-testid="page-title"]')
        expect(title.text()).toBe('My Goals')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/Header.test.js
```

Expected: FAIL — route titles missing, falls back to "Dashboard".

- [ ] **Step 3: Read current Header.vue pageTitles computed**

Run:
```bash
cd team-sync-fe
grep -A 50 "const pageTitles = computed" src/components/admin/Header.vue
```

Expected: See existing title map, missing 30+ HR routes.

- [ ] **Step 4: Extend pageTitles computed in Header.vue**

Find the `pageTitles` computed property in `team-sync-fe/src/components/admin/Header.vue` and extend it:

```javascript
const pageTitles = computed(() => {
    const route = router.currentRoute.value
    const titleMap = {
        // Dashboard
        'admin.dashboard': 'Dashboard',
        
        // Settings
        'admin.settings': 'Settings',
        
        // Analytics
        'admin.analytics': 'Analytics',
        
        // Meetings
        'admin.meetings': 'Meetings',
        
        // Notifications
        'admin.notifications': 'Notifications',
        
        // Staff Members
        'admin.staffMembers': 'Staff Members',
        'admin.staffMembers.create': 'Add Staff Member',
        'admin.staffMembers.edit': 'Edit Staff Member',
        'admin.staffMembers.detail': 'Staff Member Details',
        'admin.staffMembers.success': 'Staff Member Created',
        
        // Projects
        'admin.projects': 'Projects',
        'admin.projects.create': 'Create Project',
        'admin.projects.edit': 'Edit Project',
        'admin.projects.detail': 'Project Details',
        
        // Teams
        'admin.teams': 'Teams',
        'admin.teams.create': 'Create Team',
        'admin.teams.edit': 'Edit Team',
        'admin.teams.detail': 'Team Details',
        
        // Attendance
        'admin.attendances': 'Attendance',
        'admin.attendance.settings': 'Attendance Settings',
        'admin.attendance.periods': 'Attendance Periods',
        'admin.attendance.mismatches': 'Policy Mismatches',
        'admin.attendance.corrections': 'Attendance Corrections',
        'admin.attendance.records': 'Attendance Records',
        'admin.attendance.leave-requests': 'Leave Requests',
        'admin.attendance.holidays': 'Holiday Calendar',
        'admin.attendance.hybrid-schedules': 'Hybrid Schedules',
        'admin.attendance.overtime': 'Overtime Management',
        
        // Performance
        'admin.performance.cycles': 'Review Cycles',
        'admin.performance.cycles.create': 'Create Review Cycle',
        'admin.performance.cycles.detail': 'Review Cycle Details',
        'admin.performance.outcome-rules': 'Outcome Rules',
        'admin.performance.templates': 'Review Templates',
        'admin.performance.my-reviews': 'My Reviews',
        'admin.performance.pending-calibration': 'Pending Calibration',
        'admin.performance.review.detail': 'Review Details',
        'admin.performance.my-goals': 'My Goals',
        'admin.performance.team-goals': 'Team Goals',
        'admin.performance.feedback.received': 'Feedback Received',
        'admin.performance.feedback.given': 'Feedback Given',
        'admin.performance.feedback.give': 'Give Feedback',
        
        // Payroll (existing, keep as-is)
        'admin.payroll': 'Payroll',
        'admin.payroll.create': 'Generate Payroll',
        'admin.payroll.detail': 'Payroll Details',
        'admin.payroll.readiness': 'Payroll Readiness',
        'admin.payroll.settings': 'Payroll Settings',
        'admin.payroll.thr': 'THR Management',
        'admin.payroll.approval-matrix': 'Approval Matrix',
        
        // Staff Member Payroll (existing, keep as-is)
        'staffMember.payroll': 'My Payroll',
        'staffMember.payslip': 'Payslip Details'
    }
    
    return titleMap[route.name] || 'Dashboard'
})
```

- [ ] **Step 5: Add data-testid to page title element**

Find the page title `<h1>` in `Header.vue` template and add `data-testid`:

```vue
<h1 
    data-testid="page-title"
    class="text-2xl font-bold"
    :style="{ color: 'var(--color-text-primary)' }"
>
    {{ pageTitles }}
</h1>
```

- [ ] **Step 6: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/Header.test.js
```

Expected: PASS — all 8 tests green.

- [ ] **Step 7: Manual verification**

Run:
```bash
cd team-sync-fe
bun run dev
```

Navigate to each HR route and verify correct page title shows in header:
- `/admin/analytics` → "Analytics"
- `/admin/meetings` → "Meetings"
- `/admin/settings` → "Settings"
- `/admin/notifications` → "Notifications"
- `/admin/attendance/overtime` → "Overtime Management"
- `/admin/performance/cycles` → "Review Cycles"
- `/admin/performance/my-goals` → "My Goals"

- [ ] **Step 8: Commit**

```bash
cd team-sync-fe
git add src/components/admin/Header.vue src/tests/components/Header.test.js
git commit -m "feat: extend Header title map for 30+ HR routes"
```

---

## Final Verification

- [ ] **Step 1: Run all component tests**

Run:
```bash
cd team-sync-fe
bun run test src/tests/components/
```

Expected: All tests pass (StatsCard, MainCard, EmptyState, SearchFilter, NotificationPanel, Header).

- [ ] **Step 2: Run full test suite**

Run:
```bash
cd team-sync-fe
bun run test
```

Expected: 981 tests pass (no regressions).

- [ ] **Step 3: Manual dark mode smoke test**

Run:
```bash
cd team-sync-fe
bun run dev
```

Open browser → http://localhost:5173/admin/dashboard

Test checklist:
- [ ] Toggle dark mode via header switch
- [ ] Stats cards adapt (no white flash)
- [ ] Empty state adapts (if no data)
- [ ] Search filter adapts
- [ ] Notification panel adapts (click bell icon)
- [ ] "See all notifications" link visible in panel
- [ ] Page titles correct for all HR routes

- [ ] **Step 4: Commit verification notes**

```bash
cd team-sync-fe
git add docs/plans/on_going/2026-05-22-hr-redesign-plan-1-foundation.md
git commit -m "docs: mark Plan 1 foundation verification complete"
```

---

## Success Criteria

- ✅ All 6 shared components migrated to CSS variable dark mode
- ✅ EmptyState has 6 new icons (Video, Bell, Layout, Target, BarChart3, Calendar)
- ✅ NotificationPanel has "See all notifications" link
- ✅ Header title map extended for 30+ HR routes
- ✅ All component tests pass
- ✅ Full test suite passes (981 tests)
- ✅ Manual dark mode verification complete
- ✅ No white flash or hardcoded light colors in shared components

---

## Next Steps

After Plan 1 complete, proceed to domain-specific redesign plans:
- **Plan 2: Attendance Domain** (10 routes)
- **Plan 3: Performance Domain** (14 routes)
- **Plan 4: Core Admin** (5 routes)
- **Plan 5: Staff/Project/Team** (12 routes)

Each plan will use the migrated shared components from Plan 1.


---

## Task 1 Audit Results

**Token mapping for migration:**
- `bg-white` → `background: var(--color-surface)`
- `bg-gray-50` → `background: var(--color-surface-raised)`
- `bg-gray-100` → `background: var(--color-surface-overlay)`
- `text-gray-900` → `color: var(--color-text-primary)`
- `text-gray-500` → `color: var(--color-text-secondary)`
- `text-gray-400` → `color: var(--color-text-muted)`
- `border-gray-200` → `border-color: var(--color-border-default)`

All required tokens present in `input.css` lines 10-86. No additions needed.

---

## 2026-05-27 Execution Patch — Orchestrator Crosscheck Corrections

> **Status:** Applied before final verification in branch/worktree `hr-redesign-execution`.
> **Reason:** Direct source crosscheck found stale plan assumptions. Execution followed the corrected scope below, not the stale code examples above.

### Corrected Component Scope

- `StatsCard.vue` actual props are `title`, `value`, `subtitle`, `subtitleColor`, `iconName`, `colorScheme`, `loading`.
- Do **not** use stale plan props `icon`, `iconColor`, `trend`, `trendLabel`, or `formatter`.
- Do **not** use `import.meta.glob('lucide-vue-next')`; the component already imports Lucide icons via `import * as Icons from "lucide-vue-next"`.
- `EmptyState.vue` already includes `Video`, `Bell`, `Layout`, `Target`, `BarChart3`, and `Calendar`; no icon-add task is required.
- `EmptyState.vue` actual copy prop is `subtitle`, not `description`.
- `MainCard.vue`, `SearchFilter.vue`, and `EmptyState.vue` were already substantially tokenized. Execution avoided unnecessary rewrites.

### Corrected Header Scope

Add only missing real route names from router files:

```txt
admin.performance.team-reviews
admin.performance.goal.detail
admin.payroll.readiness
admin.payroll.settings
admin.payroll.approval-matrix
admin.payroll.adjustments
admin.payroll.comparison
admin.payroll.thr
admin.payroll.thr.detail
admin.upgrade-plan
```

Correct stale route keys:

```txt
admin.payroll        ❌ use admin.payroll.dashboard
staffMember.payslip  ❌ use staffMember.payslips.detail
```

Convert existing Indonesian Header titles/subtitles to English, including notifications, teams, staff members, attendance, projects, payroll, profile, team, and attendance self-service entries.

### Corrected TDD Checks Executed

Update tests before implementation:

- `team-sync-fe/src/tests/components/StatsCard.test.js`
  - assert root `[data-testid="stats-card"]`
  - assert no solid `bg-white`
  - assert `background: var(--color-surface)`
  - assert `border-color: var(--color-border-default)`
- `team-sync-fe/src/tests/admin/components/Header.smoke.test.js`
  - assert `admin.notifications` displays `Notifications`, not `Notifikasi`
  - assert `admin.payroll.readiness` displays `Payroll Readiness`
  - assert `admin.performance.team-reviews` displays `Team Reviews`

### Corrected Implementation Files

```txt
team-sync-fe/src/components/common/StatsCard.vue
team-sync-fe/src/components/admin/Header.vue
team-sync-fe/src/tests/components/StatsCard.test.js
team-sync-fe/src/tests/admin/components/Header.smoke.test.js
```

### Verification Evidence

```bash
cd team-sync-fe
bun run test src/tests/components/StatsCard.test.js src/tests/admin/components/Header.smoke.test.js src/tests/admin/attendance
bun run test
```

Observed result after implementation:

```txt
145 test files passed
1091 tests passed
0 failed
```

### Updated Success Criteria for Executed Scope

- ✅ `StatsCard.vue` root uses CSS variable surface/border tokens.
- ✅ `StatsCard.vue` no longer depends on solid `bg-white` card surface.
- ✅ Header title map covers the 10 verified missing routes.
- ✅ Header stale Indonesian titles in verified map converted to English.
- ✅ Stale EmptyState icon work explicitly skipped because icons already exist.
- ✅ Full frontend suite passes.
