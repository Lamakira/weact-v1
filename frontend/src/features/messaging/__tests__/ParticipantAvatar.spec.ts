import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ParticipantAvatar from '../components/ParticipantAvatar.vue'

const participant = (url: string | null) => ({
  name: 'Amakira Test',
  photo_url: url,
  profile_photo_thumbnail_url: null,
})

describe('ParticipantAvatar', () => {
  it('affiche l’image quand une URL existe', () => {
    const wrapper = mount(ParticipantAvatar, { props: { participant: participant('https://x/a.jpg') } })
    expect(wrapper.find('img').exists()).toBe(true)
  })

  it('retombe sur les initiales si l’image échoue, sans img', async () => {
    const wrapper = mount(ParticipantAvatar, { props: { participant: participant('https://x/a.jpg') } })
    await wrapper.find('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toBe('AT')
  })

  it('se réarme quand l’URL change', async () => {
    const wrapper = mount(ParticipantAvatar, { props: { participant: participant('https://x/a.jpg') } })
    await wrapper.find('img').trigger('error')
    await wrapper.setProps({ participant: participant('https://x/b.jpg') })
    expect(wrapper.find('img').exists()).toBe(true)
  })
})
