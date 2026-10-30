import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import tailwindcss from "@tailwindcss/postcss";
import autoprefixer from "autoprefixer";

export default function createThemeConfig(themeDirectory) {
    const themeId = path.basename(themeDirectory);

    return defineConfig({
        plugins: [
            laravel({
                input: [
                    path.resolve(themeDirectory, "css/app.css"),
                    path.resolve(themeDirectory, "js/app.js"),
                ],
                buildDirectory: `build/themes/${themeId}`,
                hotFile: `storage/vite/${themeId}.hot`,
            }),

            {
                name: "blade",
                handleHotUpdate({ file, server }) {
                    if (file.endsWith(".blade.php")) {
                        server.ws.send({
                            type: "full-reload",
                            path: "*",
                        });
                    }
                },
            },
        ],
        resolve: {
            alias: {
                "@": path.resolve(themeDirectory, "js/app.js"),
                livewire: path.resolve(themeDirectory, "../../../vendor/livewire/livewire/dist/livewire.esm.js"),
            },
        },
        css: {
            postcss: {
                plugins: [tailwindcss(), autoprefixer()],
            },
        },
    });
}
