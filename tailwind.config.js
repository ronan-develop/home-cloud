/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './templates/**/*.twig',
    './assets/**/*.js',
    './assets/**/*.css',
    './node_modules/@material-tailwind/html/**/*.js',
  ],
  safelist: [
    // Grille responsive explicite
    'grid-cols-2', 'sm:grid-cols-2', 'md:grid-cols-3', 'lg:grid-cols-4', '2xl:grid-cols-4',
    'gap-3',
    'grid',
    // Autres
    'glass-card',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@material-tailwind/html/utils/plugin'),
  ],
}
