/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        biblioteca: {
          50: '#f4f6f4',
          100: '#e5eae4',
          200: '#c9d4c8',
          300: '#a2b5a0',
          400: '#748f72',
          500: '#4f6f4d',
          600: '#3c5a3b',
          700: '#31482f',
          800: '#293b28',
          900: '#233123',
          950: '#111a11',
        },
        acento: {
          500: '#b5852c',
          600: '#966d22',
        },
      },
      fontFamily: {
        sans: ['"Source Sans 3"', 'system-ui', 'sans-serif'],
        serif: ['"Source Serif 4"', 'Georgia', 'serif'],
      },
    },
  },
  plugins: [],
}
