// Builds public/css/app.css (no CDN needed at runtime):
//   npx tailwindcss@3.4.17 -i resources/css/tailwind.css -o public/css/app.css --minify
module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './public/js/crm.js',
    './config/crm.php',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      },
      colors: {
        ink:    { DEFAULT: '#0e1c1a', 700: '#173330', 600: '#20423d' },
        paper:  '#f4f7f5',
        brand:  { DEFAULT: '#0f8f5b', 600: '#0c7a4d', 500: '#16a34a', 400: '#34c27f', 50: '#e8f6ef' },
        amber:  { DEFAULT: '#c77800', 500: '#e08a00', 50: '#fbf1de' },
        slate2: '#e4eae7',
      },
      boxShadow: {
        soft: '0 1px 2px rgba(14,28,26,.04), 0 8px 24px -12px rgba(14,28,26,.12)',
      },
    },
  },
};
