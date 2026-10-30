import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/postcss";
import autoprefixer from "autoprefixer";

export default defineConfig({
    plugins: [laravel({
        input: ["resources/css/filament/housekeeping/theme.css"],
        buildDirectory: "build-housekeeping",
        hotFile: "storage/vite/housekeeping.hot",
    })],
    css: { postcss: { plugins: [tailwindcss(), autoprefixer()] } },
});
