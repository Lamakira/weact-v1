import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ProductPhotosUpload from '../ProductPhotosUpload.vue'

global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
global.URL.revokeObjectURL = vi.fn()

function makeFile(name = 'photo.jpg', type = 'image/jpeg', sizeBytes = 1024): File {
  const file = new File(['x'], name, { type })
  Object.defineProperty(file, 'size', { value: sizeBytes })
  return file
}

async function selectFiles(wrapper: ReturnType<typeof mount>, files: File[]): Promise<void> {
  const input = wrapper.find('[data-testid="product-photos-input"]')
  Object.defineProperty(input.element, 'files', { value: files, writable: true })
  await input.trigger('change')
}

describe('ProductPhotosUpload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the add zone and the French hint when empty', () => {
    const wrapper = mount(ProductPhotosUpload)

    expect(wrapper.find('[data-testid="add-product-photo"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Photos du produit')
    expect(wrapper.text()).toContain('facultatif, 2 max')
    expect(wrapper.text()).toContain('Format JPG ou PNG. Taille max : 8 Mo par photo.')
    expect(wrapper.findAll('[data-testid="product-photo-preview"]')).toHaveLength(0)
  })

  it('emits update:modelValue with the accepted file', async () => {
    const wrapper = mount(ProductPhotosUpload, { props: { modelValue: [] } })
    const file = makeFile()

    await selectFiles(wrapper, [file])

    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
    expect(wrapper.emitted('update:modelValue')![0]![0]).toEqual([file])
  })

  it('renders one preview per photo with a remove button', () => {
    const wrapper = mount(ProductPhotosUpload, {
      props: { modelValue: [makeFile('a.jpg'), makeFile('b.png', 'image/png')] },
    })

    expect(wrapper.findAll('[data-testid="product-photo-preview"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-testid="remove-product-photo"]')).toHaveLength(2)
    // Album complet : la zone d'ajout disparaît à 2 photos.
    expect(wrapper.find('[data-testid="add-product-photo"]').exists()).toBe(false)
  })

  it('removing a photo emits the list without it', async () => {
    const a = makeFile('a.jpg')
    const b = makeFile('b.jpg')
    const wrapper = mount(ProductPhotosUpload, { props: { modelValue: [a, b] } })

    await wrapper.findAll('[data-testid="remove-product-photo"]')[0]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')![0]![0]).toEqual([b])
  })

  it('rejects a non-image file with a French error and no emit', async () => {
    const wrapper = mount(ProductPhotosUpload, { props: { modelValue: [] } })

    await selectFiles(wrapper, [makeFile('doc.pdf', 'application/pdf')])

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.find('[data-testid="product-photos-error"]').text()).toBe(
      'Chaque photo doit être au format JPG ou PNG.',
    )
  })

  it('rejects a file above 8 Mo with a French error and no emit', async () => {
    const wrapper = mount(ProductPhotosUpload, { props: { modelValue: [] } })

    await selectFiles(wrapper, [makeFile('big.jpg', 'image/jpeg', 9 * 1024 * 1024)])

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.find('[data-testid="product-photos-error"]').text()).toBe(
      'Chaque photo ne doit pas dépasser 8 Mo.',
    )
  })

  it('caps the selection at 2 photos and shows the quota error', async () => {
    const wrapper = mount(ProductPhotosUpload, { props: { modelValue: [makeFile('a.jpg')] } })

    await selectFiles(wrapper, [makeFile('b.jpg'), makeFile('c.jpg')])

    // Une seule place restante : b acceptée, c refusée.
    const emitted = wrapper.emitted('update:modelValue')![0]![0] as File[]
    expect(emitted).toHaveLength(2)
    expect(wrapper.find('[data-testid="product-photos-error"]').text()).toBe(
      'Vous ne pouvez joindre que 2 photos du produit.',
    )
  })

  it('displays the server error passed via the error prop', () => {
    const wrapper = mount(ProductPhotosUpload, {
      props: { modelValue: [], error: 'Chaque photo du produit ne doit pas dépasser 8 Mo.' },
    })

    expect(wrapper.find('[data-testid="product-photos-error"]').text()).toBe(
      'Chaque photo du produit ne doit pas dépasser 8 Mo.',
    )
  })
})
