import { defineConfig } from 'vite';

export default defineConfig(({ command }) => ({
  // Production assets are served from the public/build directory by PHP.
  // Keep dev URLs rooted normally for the Vite development server.
  base: command === 'build' ? '/build/' : '/',
  publicDir: false,
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'resources/js/app.js',
    },
  },
}));
