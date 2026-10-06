import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwind from "@tailwindcss/vite";
export default defineConfig({
  plugins: [react(), tailwind()],
  server: {
    host: "127.0.0.1",
    proxy: process.env.DEV_API_PROXY_TARGET
      ? {
          "/api": {
            target: process.env.DEV_API_PROXY_TARGET,
            changeOrigin: true,
          },
          "/sanctum": {
            target: process.env.DEV_API_PROXY_TARGET,
            changeOrigin: true,
          },
        }
      : undefined,
  },
  build: { outDir: "dist" },
});
