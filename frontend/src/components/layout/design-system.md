# Design System - Dashboard Layout

## Vibe: Creative Flow (Soft & Organic)

**Direction:** Accessible and fluid. Very rounded shapes, soft gradients, pastel tones mixed with teal. Friendly, modern, inviting.

## Color Palette

| Token | Value | Usage |
|-------|-------|-------|
| Primary | `#198496` | Buttons, active states, accents |
| Primary Light | `rgba(25, 132, 150, 0.1)` / `teal-50` | Backgrounds, hover states |
| Primary Gradient | `from-[#198496] to-teal-300` | Avatars, decorative elements |
| Background | `slate-50` | Page background |
| Card Background | `white` | Cards, sidebar |
| Text Primary | `slate-800` | Headings |
| Text Secondary | `slate-500` | Body text, subtitles |
| Text Muted | `slate-400` | Captions, placeholders |
| Border | `gray-50` / `teal-50` | Card borders |

## Border Radius

| Element | Radius |
|---------|--------|
| Layout Container | `rounded-[40px]` |
| Cards | `rounded-[24px]` or `rounded-2xl` |
| Icon Containers | `rounded-2xl` |
| Badges/Pills | `rounded-full` |
| Buttons | `rounded-full` |

## Shadows

| Element | Shadow |
|---------|--------|
| Layout Container | `shadow-xl shadow-teal-900/5` |
| Cards | `shadow-sm` → `shadow-md` (hover) |
| Primary Elements | `shadow-lg shadow-teal-500/30` |
| Active Sidebar Item | `shadow-sm` |

## Spacing

| Context | Value |
|---------|-------|
| Layout Padding | `p-12` |
| Section Gap | `gap-10` / `mb-10` |
| Card Grid Gap | `gap-6` |
| Card Internal Padding | `p-6` |
| Sidebar Width (collapsed) | `w-20` |
| Sidebar Width (expanded) | `w-64` |
| Header Height | `h-20` |

## Typography

| Element | Classes |
|---------|---------|
| Page Title | `text-3xl font-bold text-slate-800` |
| Section Title | `text-lg font-semibold text-slate-800` |
| Card Title | `font-bold text-slate-800` |
| Card Subtitle | `text-sm text-slate-400` |
| Badge | `text-xs font-bold uppercase tracking-wider` |

## Component Patterns

### Sidebar Item (Collapsed)
```html
<div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-[#198496] shadow-sm cursor-pointer">
  <Icon class="w-5 h-5" />
</div>
```

### Sidebar Item (Inactive)
```html
<div class="w-12 h-12 rounded-2xl flex items-center justify-center text-slate-300 hover:bg-white hover:text-[#198496] transition-all cursor-pointer">
  <Icon class="w-5 h-5" />
</div>
```

### Card
```html
<div class="p-6 bg-white rounded-[24px] shadow-sm border border-gray-50 hover:shadow-md transition-shadow cursor-pointer">
  <div class="w-12 h-12 bg-teal-50 rounded-2xl mb-4 flex items-center justify-center text-[#198496]">
    <Icon class="w-5 h-5" />
  </div>
  <h3 class="font-bold text-slate-800 mb-1">Title</h3>
  <p class="text-sm text-slate-400">Subtitle</p>
</div>
```

### Header Badge
```html
<div class="px-4 py-1.5 rounded-full bg-teal-50 text-[#198496] text-xs font-bold uppercase tracking-wider">
  Face Dashboard
</div>
```

### Background Decoration
```html
<div class="absolute top-0 right-0 w-96 h-96 bg-teal-50 rounded-full blur-3xl -mr-48 -mt-48 opacity-50"></div>
```

## Transitions

All interactive elements: `transition-all` or specific:
- Colors: `transition-colors`
- Shadows: `transition-shadow`
- Transform: `transition-transform`

## Responsive Breakpoints

| Viewport | Sidebar | Header |
|----------|---------|--------|
| Desktop (> 1024px) | Expanded (w-64) or Collapsed (w-20) | Full |
| Tablet (768-1024px) | Collapsed (w-20) | Full |
| Mobile (< 768px) | Hidden (overlay on demand) | Hamburger menu |
