import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const root = resolve(import.meta.dirname, '../demo');

// Usa o mesmo HTML/CSS/JS da demo e injeta o runtime PHP no navegador.
export default defineConfig({
    root,
    base: './',
    build: {
        outDir: resolve(import.meta.dirname, 'dist'),
        emptyOutDir: true,
        target: 'es2022',
    },
    server: {
        fs: { allow: [resolve(import.meta.dirname, '..')] },
    },
    optimizeDeps: {
        exclude: ['@php-wasm/web', '@php-wasm/universal'],
    },
    plugins: [
        {
            // Só a versão 8.3 do PHP vai para o site. As outras e a extensão intl viram stubs.
            name: 'php-wasm-only-8-3',
            enforce: 'pre',
            resolveId(source) {
                if (/^@php-wasm\/web-\d-\d$/.test(source) && source !== '@php-wasm/web-8-3' || /icu\.dat$|intl\.so/.test(source)) {
                    return '\0php-wasm-stub';
                }
                return null;
            },
            load(id) {
                if (id !== '\0php-wasm-stub') return null;
                return 'const off = () => { throw new Error("Versão do PHP não incluída no build."); };\n'
                    + 'export const getPHPLoaderModule = off;\nexport const getIntlExtensionPath = off;\nexport default "";';
            },
        },
        {
            // O runtime faz "import arquivo from './php.wasm'" esperando a URL do arquivo.
            name: 'php-wasm-as-url',
            enforce: 'pre',
            async resolveId(source, importer) {
                if (!/\.(wasm|so|dat)$/.test(source)) return null;
                const resolved = await this.resolve(source, importer, { skipSelf: true });
                return resolved ? `${resolved.id}?url` : null;
            },
        },
        {
            name: 'pix-php-runtime',
            transformIndexHtml: {
                order: 'pre',
                handler(html) {
                const runtime = '<script type="module" src="../pages/runtime.js"></script>';
                return html.replace('<script type="module" src="assets/app.js">', `${runtime}\n<script type="module" src="assets/app.js">`);
                },
            },
        },
    ],
});
