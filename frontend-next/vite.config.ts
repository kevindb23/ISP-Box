import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  build: {
    outDir: '../public/build-next',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'src/main.ts',
    },
  },
});
