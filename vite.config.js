import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import path from 'path';
import { execSync } from 'child_process';

const viteDevServerUrl = process.env.VITE_DEV_SERVER_URL;

let hasPhp = false;
try {
  execSync('php -v', { stdio: 'ignore' });
  hasPhp = true;
} catch {
  hasPhp = false;
}

export default defineConfig({
  base: './',
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, 'resources/js'),
      '@actions': path.resolve(import.meta.dirname, 'resources/js/actions'),
      '@routes': path.resolve(import.meta.dirname, 'resources/js/routes'),
    },
  },
  plugins: [
    laravel({
      input: ['resources/js/app.js', 'resources/css/app.css'],
      refresh: true,
    }),
    wayfinder({
      command: hasPhp ? 'php artisan wayfinder:generate' : (process.platform === 'win32' ? 'cmd /c exit 0' : 'true'),
    }),
  ],
  build: {
    outDir: 'public/build',
    manifest: 'manifest.json',
    minify: 'esbuild',
    cssMinify: true,
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules/chart.js')) {
            return 'vendor-chart';
          }
          if (id.includes('node_modules/alpinejs')) {
            return 'vendor-alpine';
          }
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: viteDevServerUrl ? new URL(viteDevServerUrl).hostname : 'localhost',
    },
    ...(viteDevServerUrl ? { origin: viteDevServerUrl } : {}),
  },
});

