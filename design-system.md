# CRM Telegram Mini App — Design System

## Color Palette (Telegram Dark)

### Background
| Token | OKLCH | Usage |
|-------|-------|-------|
| `--bg` | `oklch(11% 0.008 260)` | Primary surface, body background |
| `--surface` | `oklch(16% 0.01 260)` | Cards, elevated surfaces |
| `--surface2` | `oklch(21% 0.009 260)` | Inputs, hover states |
| `--surface3` | `oklch(24% 0.008 260)` | Hover states without border |

### Brand
| Token | OKLCH | Usage |
|-------|-------|-------|
| `--primary` | `oklch(60% 0.13 220)` | Telegram Blue, CTAs, active states |
| `--primary-fg` | `oklch(98% 0.003 212)` | Text on primary |

### Text
| Token | OKLCH | Usage |
|-------|-------|-------|
| `--fg` | `oklch(94% 0.005 260)` | Primary text |
| `--fg-muted` | `oklch(60% 0.015 260)` | Secondary text |
| `--fg-subtle` | `oklch(45% 0.012 260)` | Disabled, placeholders |
| `--fg-data` | `oklch(88% 0.004 260)` | Numbers/data — not white, not grey |

### Border
| Token | OKLCH | Usage |
|-------|-------|-------|
| `--border` | `oklch(27% 0.009 260)` | Default borders |
| `--border-focus` | `oklch(65% 0.15 212)` | Focus rings |

### Semantic
| Token | OKLCH | Usage |
|-------|-------|-------|
| `--success` | `oklch(58% 0.11 158)` | Success states, money |
| `--success-bg` | `oklch(19% 0.025 158)` | Success backgrounds |
| `--warning` | `oklch(65% 0.12 50)` | Warning, edited flags |
| `--warning-bg` | `oklch(20% 0.03 50)` | Warning backgrounds |
| `--error` | `oklch(55% 0.15 20)` | Errors, panic |
| `--error-bg` | `oklch(19% 0.04 20)` | Error backgrounds |

### Charts
| Token | OKLCH |
|-------|-------|
| `--chart-1` | `oklch(65% 0.15 212)` |
| `--chart-2` | `oklch(65% 0.14 158)` |
| `--chart-3` | `oklch(72% 0.15 55)` |
| `--chart-4` | `oklch(62% 0.18 25)` |
| `--chart-5` | `oklch(58% 0.14 292)` |

## Typography

### Font Families
- **Display**: `Space Grotesk` (Google Fonts)
- **Body**: `Geist` (Google Fonts, fallback: system-ui)

### Scale
| Class | Size | Weight | Usage |
|-------|------|--------|-------|
| `text-xs` | 12px | 400 | Labels, captions |
| `text-sm` | 14px | 400-500 | Body, descriptions |
| `text-lg` | 18px | 600 | Card titles |
| `text-xl` | 20px | 700 | Headers |
| `text-2xl` | 24px | 700 | KPI numbers |

## Spacing

- Base unit: 4px
- Padding: 16px (page), 12px (cards)
- Gap: 12px (grid), 8px (list items)
- Border radius: 8px (cards), 12px (modals)

## Motion

| Token | Value | Usage |
|-------|-------|-------|
| `--duration-fast` | 150ms | Micro-interactions |
| `--duration-base` | 250ms | Standard transitions |
| `--duration-slow` | 400ms | Page transitions |
| `--ease-standard` | `cubic-bezier(0.25, 0.1, 0.25, 1)` | Default easing |
| `--ease-spring` | `cubic-bezier(0.34, 1.56, 0.64, 1)` | Button press |

## Component Patterns

### Cards
```html
<div class="bg-surface border border-border rounded-xl p-4">
```

### Buttons (height: 44px min)
```html
<button class="btn w-full bg-primary text-primary-fg font-semibold text-sm rounded-lg">
```

### Status Badges
| Status | Color | Background |
|--------|-------|------------|
| created | primary | primary/10 |
| assigned | secondary | secondary/10 |
| at_warehouse | warning | warning/10 |
| completed | success | success/10 |

### Warning Highlight (is_edited)
```html
<div class="bg-warningBg border-l-4 border-warning rounded-r-xl p-3">
```

## Usage

```html
<script>
  tailwind.config={
    darkMode:'class',
    theme:{extend:{
      colors:{
        bg:         'oklch(11% 0.008 260)',
        surface:    'oklch(16% 0.01 260)',
        surface2:   'oklch(21% 0.009 260)',
        primary:    'oklch(60% 0.13 220)',
        fg:         'oklch(94% 0.005 260)',
        // ...
      }
    }}
  }
</script>
```


## Правило 90/10

Акцентные цвета (`primary`, `success`, `warning`, `error`) должны занимать не более **10% площади** экрана. Оставшиеся 90% — нейтральные тона (`bg`, `surface`, `surface2`, `surface3`, `fg`, `fg-muted`).

- Используй акцент только для интерактивных элементов, статусов и важных сигналов.
- Не окрашивай фоны целых секций в акцентный цвет — только иконки, бейджи, тонкие бордеры.
- Приглушённые значения OKLCH (низкая хрома) помогают держать цвет в рамках 10% даже при частом появлении.

## tabular-nums

Для всех числовых данных (цены, количества, ID, даты) используй `font-variant-numeric: tabular-nums` (`font-variant-numeric` в CSS или класс `tabular-nums` в Tailwind).

```html
<span class="tabular-nums text-fg-data">12 345,00 ₽</span>
```

**Почему важно:**
- Цифры одинаковой ширины не «прыгают» при обновлении данных в реальном времени.
- Колонки в таблицах выравниваются по правому краю без смещений.
- Токен `--fg-data` (`oklch(88% 0.004 260)`) специально создан для числовых данных: чуть теплее чисто-белого, но отличается от обычного `fg-muted`, визуально выделяя данные среди текста.
