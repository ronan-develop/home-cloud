
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    symfonyPlugin(),
    tailwindcss(),
  ],
  root: '.',
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: './assets/app.js',
    },
  },
  server: {
    watch: {
      usePolling: true,
    },
    port: 5173,
    strictPort: true,
  },
});
