/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.html', './script.js'],
  theme: {
    extend: {
      colors: {
        navy: { 950: '#071829', 900: '#0B223B', 800: '#112A46', 700: '#1C3F63' },
        signal: '#FF6B00'
      },
      fontFamily: { sans: ['Manrope', 'sans-serif'] },
      boxShadow: { lift: '0 24px 60px -28px rgba(11,34,59,.34)' }
    }
  },
  plugins: []
};
