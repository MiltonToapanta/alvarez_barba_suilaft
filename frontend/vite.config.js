import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Vite corre detrás de Nginx (http://localhost:8080); el HMR vuelve por ese mismo puerto.
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    hmr: { clientPort: 8080 },
    watch: { usePolling: true },
  },
});
