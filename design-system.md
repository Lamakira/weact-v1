# WEACT Design System

**Vibe:** Minimalist & Clean
**Scale:** Refined (small, elegant sizing)
**Generated:** 2026-02-02
**Last Updated:** 2026-02-02

## Design Principles

- Airy, lots of whitespace
- Clean typography
- Subtle borders and shadows
- Smooth, elegant transitions
- Brand color: #198496 (teal)
- Accessible by default (ARIA, keyboard navigation, focus management)

---

## Header Layout (User-Defined)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│  WEACT        Trouver des faces   Missions   Ressources    [Poster] [Devenir] Se co │
│  (logo)            (center nav links)                       (CTAs)            (link)│
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

**Left:** Logo (WEACT - PNG image or "WE" black + "ACT" teal)
**Center:** Navigation links (Trouver des faces, Missions, Ressources)
**Right:**
1. "Poster une mission" - **Primary solid button** (teal bg, white text)
2. "Devenir une face" - **Secondary outline button** (teal border, teal text)
3. "Se connecter" - **Text link** (gray)

---

## Design Tokens

### Colors

| Token | Value | Usage |
|-------|-------|-------|
| Primary | `#198496` | Brand color, CTAs, links |
| Primary Hover | `#146c7a` | Hover state for primary |
| Primary Light | `#198496/5` | Subtle hover backgrounds |
| Text Dark | `text-gray-900` | Headings, logo |
| Text Medium | `text-gray-700` | Nav links, body text |
| Text Light | `text-gray-500` | Secondary text |
| Border | `border-gray-200` | Subtle borders |
| Border Light | `border-gray-100` | Dividers |
| Background | `bg-white` | Main background |
| Background Hover | `bg-gray-100` | Interactive hover states |

### Typography

| Element | Classes |
|---------|---------|
| Logo "WE" | `font-bold text-gray-900` |
| Logo "ACT" | `font-bold text-[#198496]` |
| Nav Links | `text-sm text-gray-700 hover:text-[#198496] transition-colors` |
| Button Text | `text-sm font-medium` |
| Body Text | `text-sm text-gray-700` |

### Buttons

**Primary CTA (Poster une mission):**
```html
<button class="text-sm font-medium bg-[#198496] text-white px-5 py-2 rounded-md hover:bg-[#146c7a] transition-colors">
  Poster une mission
</button>
```

**Secondary CTA (Devenir une face):**
```html
<button class="text-sm font-medium text-[#198496] border border-[#198496] px-5 py-2 rounded-md hover:bg-[#198496]/5 transition-colors">
  Devenir une face
</button>
```

**Text Link (Se connecter):**
```html
<a class="text-sm text-gray-700 hover:text-[#198496] transition-colors">
  Se connecter
</a>
```

**Mobile Full-Width CTA:**
```html
<a class="w-full text-center py-3 text-sm font-medium bg-[#198496] text-white rounded-md hover:bg-[#146c7a] transition-colors">
  Button Text
</a>
```

### Navigation Links

```html
<a class="text-sm text-gray-700 hover:text-[#198496] transition-colors">
  Link text
</a>
```

### Spacing

| Context | Value |
|---------|-------|
| Container max-width | `max-w-7xl` |
| Container padding | `px-4` or `px-6` |
| Header padding | `py-4` |
| Nav link gap | `gap-8` |
| Button gap | `gap-3` or `gap-4` |
| Button padding | `px-5 py-2` |
| Mobile menu padding | `px-4 py-6` |
| Mobile nav spacing | `space-y-4` (links), `space-y-3` (CTAs) |

### Border Radius

| Element | Value |
|---------|-------|
| Buttons | `rounded-md` |
| Mobile menu button | `rounded-lg` |
| Mobile menu container | (none - sharp edges) |

### Transitions

| Type | Classes |
|------|---------|
| Default | `transition-colors` |
| All properties | `transition-all duration-200` |
| Menu enter | `transition duration-300 ease-out` |
| Menu leave | `transition duration-200 ease-in` |

### Z-Index Scale

| Element | Value |
|---------|-------|
| Mobile menu | `z-[100]` |
| Dropdowns | `z-50` |
| Modals | `z-[200]` |

---

## Responsive Breakpoints

| Prefix | Min Width | Target | Usage |
|--------|-----------|--------|-------|
| (none) | 0px | Mobile | Default styles |
| `sm` | 640px | Large phones | - |
| `md` | 768px | Tablets | - |
| `lg` | 1024px | Laptops | **Desktop nav visible** |
| `xl` | 1280px | Desktops | - |

**Key patterns:**
- Mobile menu visible: `lg:hidden`
- Desktop nav visible: `hidden lg:flex`

---

## Accessibility Patterns

### Mobile Menu Button

```html
<button
  ref="mobileMenuButtonRef"
  @click="toggleMobileMenu"
  class="lg:hidden p-2 text-gray-700 hover:text-[#198496] transition-colors rounded-lg hover:bg-gray-100"
  :aria-expanded="isMobileMenuOpen"
  aria-controls="mobile-menu"
  aria-label="Ouvrir le menu de navigation"
  data-testid="header-mobile-menu-button"
>
  <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
  <X v-else class="w-6 h-6" />
</button>
```

### Mobile Menu Container

```html
<div
  ref="mobileMenuRef"
  id="mobile-menu"
  class="absolute top-full left-0 w-full bg-white border-b border-gray-200 shadow-lg lg:hidden z-[100]"
  role="navigation"
  aria-label="Menu de navigation mobile"
  data-testid="header-mobile-menu"
>
  <!-- Content -->
</div>
```

### Required Accessibility Features

1. **aria-expanded** on toggle button (synced with menu state)
2. **aria-controls** linking button to menu id
3. **aria-label** on icon-only buttons
4. **role="navigation"** on nav containers
5. **data-testid** on all interactive elements
6. **Escape key** closes menu and returns focus to button
7. **Click outside** closes menu
8. **Focus management** - first focusable element receives focus when menu opens

---

## Mobile Menu Pattern (Complete)

### Script Setup

```typescript
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Menu, X } from 'lucide-vue-next'

const isMobileMenuOpen = ref(false)
const mobileMenuRef = ref<HTMLElement | null>(null)
const mobileMenuButtonRef = ref<HTMLElement | null>(null)

function toggleMobileMenu(): void {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

function closeMobileMenu(): void {
  isMobileMenuOpen.value = false
}

// Handle Escape key press
function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && isMobileMenuOpen.value) {
    closeMobileMenu()
    mobileMenuButtonRef.value?.focus()
  }
}

// Handle click outside
function handleClickOutside(event: MouseEvent): void {
  if (!isMobileMenuOpen.value) return
  const target = event.target as Node
  const isClickInsideMenu = mobileMenuRef.value?.contains(target)
  const isClickOnButton = mobileMenuButtonRef.value?.contains(target)
  if (!isClickInsideMenu && !isClickOnButton) {
    closeMobileMenu()
  }
}

// Focus first element when menu opens
watch(isMobileMenuOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    const firstLink = mobileMenuRef.value?.querySelector('a, button') as HTMLElement | null
    firstLink?.focus()
  }
})

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('click', handleClickOutside)
})
```

### Template Animation

```vue
<Transition
  enter-active-class="transition duration-300 ease-out"
  enter-from-class="opacity-0 -translate-y-4"
  enter-to-class="opacity-100 translate-y-0"
  leave-active-class="transition duration-200 ease-in"
  leave-from-class="opacity-100 translate-y-0"
  leave-to-class="opacity-0 -translate-y-4"
>
  <div
    v-if="isMobileMenuOpen"
    ref="mobileMenuRef"
    id="mobile-menu"
    class="absolute top-full left-0 w-full bg-white border-b border-gray-200 shadow-lg lg:hidden z-[100]"
    role="navigation"
    aria-label="Menu de navigation mobile"
    data-testid="header-mobile-menu"
  >
    <div class="px-4 py-6 flex flex-col space-y-6">
      <!-- Navigation Links -->
      <nav class="flex flex-col space-y-4">
        <RouterLink to="/path" class="text-sm text-gray-700 hover:text-[#198496] transition-colors" @click="closeMobileMenu">
          Link Text
        </RouterLink>
      </nav>

      <!-- Divider -->
      <div class="h-[1px] w-full bg-gray-100"></div>

      <!-- CTAs -->
      <div class="flex flex-col space-y-3">
        <!-- Primary -->
        <RouterLink to="/path" class="w-full text-center py-3 text-sm font-medium bg-[#198496] text-white rounded-md hover:bg-[#146c7a] transition-colors" @click="closeMobileMenu">
          Primary CTA
        </RouterLink>
        <!-- Secondary -->
        <RouterLink to="/path" class="w-full text-center py-3 text-sm font-medium text-[#198496] border border-[#198496] rounded-md hover:bg-[#198496]/5 transition-colors" @click="closeMobileMenu">
          Secondary CTA
        </RouterLink>
        <!-- Text Link -->
        <RouterLink to="/path" class="w-full text-center py-3 text-sm text-gray-700 hover:text-[#198496] transition-colors" @click="closeMobileMenu">
          Text Link
        </RouterLink>
      </div>
    </div>
  </div>
</Transition>
```

---

## Footer Layout (User-Defined)

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│  WEACT (logo)                   TROUVER DES FACES    ENTREPRISE    RESSOURCES        │
│  Marketplace béninoise          • Parcourir les      • Légal       • Blog            │
│  du casting.                      profils            • À propos    • FAQ             │
│                                 • Publier une        • Contact     • Guide de        │
│  [IG] [FB] [X] [YT]               mission                            démarrage       │
│                                 • Tarifs                           • Support         │
│                                   producteurs                                         │
├──────────────────────────────────────────────────────────────────────────────────────┤
│  © 2026 WeAct. Tous droits réservés.                    Made with ❤️ in Benin        │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

**Structure:**
- **Left column:** Logo, tagline, social icons (Instagram, Facebook, Twitter, YouTube)
- **3 Navigation columns:** TROUVER DES FACES, ENTREPRISE, RESSOURCES
- **Bottom bar:** Copyright left, "Made with ❤️ in Benin" right

### Footer Design Tokens

| Element | Classes |
|---------|---------|
| Footer container | `bg-white border-t border-gray-200` |
| Footer padding | `py-12 lg:py-16` |
| Column grid | `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-8` |
| Column heading | `text-sm font-bold text-gray-900 tracking-wider` |
| Footer links | `text-sm text-gray-700 hover:text-[#198496] transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[#198496]` |
| Social icons | `text-gray-500 hover:text-[#198496] transition-colors duration-200` |
| Bottom bar | `border-t border-gray-100 flex flex-col md:flex-row justify-between` |
| Copyright/attribution | `text-sm text-gray-500` |

### Footer Accessibility

- `role="contentinfo"` on footer element
- `aria-label` on all social icon links (e.g., "Suivez-nous sur Instagram")
- `aria-hidden="true"` on decorative emoji with `.sr-only` alternative text
- `focus-visible:ring-2` on all interactive elements
- `target="_blank" rel="noopener noreferrer"` on external social links

---

## Usage Guidelines

1. **Respect the exact button order:** Poster une mission (primary) → Devenir une face (outline) → Se connecter (text)
2. **Keep navigation centered** between logo and CTAs
3. **Use consistent spacing** - `gap-8` for nav links, `gap-3` for buttons
4. **Subtle hover states** - color transitions only, no dramatic effects
5. **Brand color sparingly** - Use #198496 for CTAs and hover accents
6. **Mobile-first responsive** - Use `lg:` breakpoint for desktop-specific styles
7. **Always include accessibility** - ARIA attributes, keyboard support, focus management
8. **Close mobile menu** - On link click, route change, Escape key, and click outside
9. **Use data-testid** - On all interactive elements for testing
10. **z-index consistency** - Use `z-[100]` for mobile menus, higher for modals
