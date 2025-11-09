/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './templates/**/*.twig',
    './assets/**/*.js',
    './assets/**/*.css',
    './node_modules/@material-tailwind/html/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@material-tailwind/html/utils/plugin'),
  ],
}
