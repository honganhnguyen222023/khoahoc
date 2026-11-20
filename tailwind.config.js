/** @type {import('tailwindcss').Config} – giống hệt theme WordPress, chỉ đổi nơi quét class. */
module.exports = {
  content: ['./app/**/*.{ts,tsx}', './components/**/*.{ts,tsx}', './lib/**/*.{ts,tsx}', './public/js/app.js'],
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: 'var(--kh-primary)', dark: 'var(--kh-primary-dark)', soft: 'var(--kh-primary-soft)', mint: 'var(--kh-mint)' },
        ink: 'var(--kh-ink)',
        body: 'var(--kh-body)',
        muted: '#6B7280',
        line: '#E3EAE6',
        surface: '#F2F7F4',
        deep: 'var(--kh-deep)',
        accent: '#175CFF',
        sale: '#C2410C',
      },
      fontFamily: {
        head: ['"Be Vietnam Pro"', 'system-ui', '-apple-system', '"Segoe UI"', 'sans-serif'],
        sans: ['Inter', '"Be Vietnam Pro"', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      maxWidth: { box: '1140px', content: '1040px' },
      borderRadius: { card: '14px', box: '20px' },
      boxShadow: { card: '0 1px 2px rgba(16,38,28,.04), 0 8px 24px -12px rgba(16,38,28,.12)', pop: '0 18px 40px -16px rgba(16,38,28,.28)' },
    },
  },
  plugins: [],
};
