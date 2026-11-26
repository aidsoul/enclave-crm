/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './public/**/*.html',
  ],
  theme: {
    extend: {
      colors: {
        bg: 'oklch(11% 0.008 260 / <alpha-value>)',
        surface: 'oklch(16% 0.01 260 / <alpha-value>)',
        surface2: 'oklch(21% 0.009 260 / <alpha-value>)',
        primary: 'oklch(60% 0.13 220 / <alpha-value>)',
        'primary-fg': 'oklch(98% 0.003 212 / <alpha-value>)',
        fg: 'oklch(94% 0.005 260 / <alpha-value>)',
        'fg-muted': 'oklch(60% 0.015 260 / <alpha-value>)',
        'fg-subtle': 'oklch(45% 0.012 260 / <alpha-value>)',
        border: 'oklch(27% 0.009 260 / <alpha-value>)',
        success: 'oklch(58% 0.11 158 / <alpha-value>)',
        'success-bg': 'oklch(19% 0.025 158 / <alpha-value>)',
        warning: 'oklch(65% 0.12 50 / <alpha-value>)',
        'warning-bg': 'oklch(20% 0.03 50 / <alpha-value>)',
        error: 'oklch(55% 0.15 20 / <alpha-value>)',
        'error-bg': 'oklch(19% 0.04 20 / <alpha-value>)',
        surface3: 'oklch(24% 0.008 260 / <alpha-value>)',
        'fg-data': 'oklch(88% 0.004 260 / <alpha-value>)',
      },
      fontFamily: {
        display: ['Space Grotesk', 'sans-serif'],
        body: ['Geist', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
