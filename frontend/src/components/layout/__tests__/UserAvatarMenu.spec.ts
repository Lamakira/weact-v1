import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UserAvatarMenu from '../UserAvatarMenu.vue'

describe('UserAvatarMenu', () => {
  function mountMenu(props = {}) {
    return mount(UserAvatarMenu, {
      attachTo: document.body,
      props: {
        userName: 'John Doe',
        userEmail: 'john@example.com',
        profileRoute: '/face/profile',
        ...props,
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })
  }

  it('opens the menu on avatar click and shows the initial', async () => {
    const wrapper = mountMenu()
    expect(wrapper.find('[data-testid="avatar-dropdown"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="header-avatar"]').text()).toBe('J')

    await wrapper.find('[data-testid="header-avatar"]').trigger('click')

    expect(wrapper.find('[data-testid="avatar-dropdown"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('closes on Escape', async () => {
    const wrapper = mountMenu()
    await wrapper.find('[data-testid="header-avatar"]').trigger('click')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="avatar-dropdown"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('closes on outside click', async () => {
    const wrapper = mountMenu()
    await wrapper.find('[data-testid="header-avatar"]').trigger('click')

    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="avatar-dropdown"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('emits logout and closes the menu', async () => {
    const wrapper = mountMenu()
    await wrapper.find('[data-testid="header-avatar"]').trigger('click')

    await wrapper.find('[data-testid="dropdown-logout-button"]').trigger('click')

    expect(wrapper.emitted('logout')).toHaveLength(1)
    expect(wrapper.find('[data-testid="avatar-dropdown"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
