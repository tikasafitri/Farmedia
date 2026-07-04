import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import viteCompression from "vite-plugin-compression"; // Tambahkan ini

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        viteCompression(), // Tambahkan ini untuk otomatis membuat kompresi .gz
    ],
    server: {
        hmr: {
            host: "localhost",
            protocol: "ws",
        },
    },
});
