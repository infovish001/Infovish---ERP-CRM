import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    plugins: [laravel({ input: ['resources/js/app.tsx'], refresh: true }), react(), tailwindcss()],
    test: { environment: 'jsdom', include: ['resources/js/**/*.test.tsx'] },
});
