import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import UApp from '@nuxt/ui/components/App.vue'
import StatusBadge from '~/components/StatusBadge.vue'
import BarList from '~/components/charts/BarList.vue'
import MeterBar from '~/components/charts/MeterBar.vue'

describe('StatusBadge', () => {
  it('shows the human label for a status', async () => {
    const wrapper = await mountSuspended(StatusBadge, {
      props: { kind: 'task', value: 'in_progress' }
    })
    expect(wrapper.text()).toBe('In Progress')
  })

  it('shows a dash for unknown values', async () => {
    const wrapper = await mountSuspended(StatusBadge, { props: { kind: 'project', value: null } })
    expect(wrapper.text()).toBe('—')
  })
})

describe('BarList', () => {
  const items = [
    { key: 'a', label: 'Active', value: 4 },
    { key: 'b', label: 'Planning', value: 2 },
    { key: 'c', label: 'Cancelled', value: 0 }
  ]

  // Tooltips need the provider that <UApp> sets up in app.vue, so mount the
  // chart the way the real app renders it: inside UApp.
  const mountInApp = () =>
    mountSuspended(
      defineComponent({
        setup: () => () => h(UApp, null, { default: () => h(BarList, { items }) })
      })
    )

  it('prints every value (the tooltip never hides information)', async () => {
    const wrapper = await mountInApp()
    const text = wrapper.text()
    expect(text).toContain('Active')
    expect(text).toContain('4')
    expect(text).toContain('Cancelled')
    expect(text).toContain('0')
  })

  it('scales bars to the largest value and draws no bar for zero', async () => {
    const wrapper = await mountInApp()
    const widths = wrapper.findAll('[style*="width"]').map((el) => el.attributes('style'))
    expect(widths).toEqual(['width: 100%;', 'width: 50%;'])
  })

  it('gives each row an accessible name', async () => {
    const wrapper = await mountInApp()
    expect(wrapper.find('[aria-label="Planning: 2"]').exists()).toBe(true)
  })
})

describe('MeterBar', () => {
  it('exposes its value to assistive technology and clamps the fill', async () => {
    const wrapper = await mountSuspended(MeterBar, { props: { value: 140, label: 'Done' } })
    const meter = wrapper.find('[role="meter"]')
    expect(meter.attributes('aria-valuenow')).toBe('140')
    expect(meter.attributes('aria-label')).toBe('Done')
    expect(wrapper.find('[style]').attributes('style')).toBe('width: 100%;')
  })
})
