import {
    defineConfig,
    loadEnv,
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devHost = env.VITE_DEV_HOST || '0.0.0.0';
    const devPort = env.VITE_DEV_PORT ? Number(env.VITE_DEV_PORT) : 5173;
    const hmrHost = env.VITE_HMR_HOST || undefined;
    const hmrProtocol = env.VITE_HMR_PROTOCOL || undefined;
    const hmrClientPort = env.VITE_HMR_CLIENT_PORT
        ? Number(env.VITE_HMR_CLIENT_PORT)
        : undefined;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            cors: true,
            host: devHost,
            port: devPort,
            hmr: hmrHost || hmrProtocol || hmrClientPort ? {
                host: hmrHost,
                protocol: hmrProtocol,
                clientPort: hmrClientPort,
            } : undefined,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
