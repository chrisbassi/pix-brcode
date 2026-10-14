/*
 * Roda a lib PHP de verdade no navegador, com PHP compilado para WebAssembly.
 * É assim que a demo funciona no GitHub Pages, que não executa PHP no servidor.
 *
 * Os arquivos da lib, do QR Code e o demo/api.php vão para um sistema de
 * arquivos virtual. As chamadas que o app.js faria para api.php passam por aqui.
 */
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadWebRuntime } from '@php-wasm/web';

const sources = {
    ...prefix('/www/src/', import.meta.glob('../src/**/*.php', { query: '?raw', import: 'default', eager: true }), '../src/'),
    ...prefix('/www/vendor/bacon/', import.meta.glob('../vendor/bacon/bacon-qr-code/src/**/*.php', { query: '?raw', import: 'default', eager: true }), '../vendor/bacon/bacon-qr-code/src/'),
    ...prefix('/www/vendor/dasprid/', import.meta.glob('../vendor/dasprid/enum/src/**/*.php', { query: '?raw', import: 'default', eager: true }), '../vendor/dasprid/enum/src/'),
    ...prefix('/www/demo/', import.meta.glob('../demo/api.php', { query: '?raw', import: 'default', eager: true }), '../demo/'),
};

// Autoload enxuto no lugar do gerado pelo Composer, que usa caminhos absolutos.
sources['/www/vendor/autoload.php'] = `<?php
spl_autoload_register(function (string $class): void {
    $map = [
        'ChrisBassi\\\\PixBrCode\\\\' => '/www/src/',
        'BaconQrCode\\\\' => '/www/vendor/bacon/',
        'DASPRiD\\\\Enum\\\\' => '/www/vendor/dasprid/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
`;

function prefix(target, files, base) {
    return Object.fromEntries(Object.entries(files).map(([path, code]) => [target + path.slice(base.length), code]));
}

async function boot() {
    const php = new PHP(await loadWebRuntime('8.3'));

    for (const [path, code] of Object.entries(sources)) {
        php.mkdir(path.slice(0, path.lastIndexOf('/')));
        php.writeFile(path, code);
    }

    return new PHPRequestHandler({ phpFactory: async () => php, documentRoot: '/www/demo' });
}

const ready = boot();

window.pixApi = {
    ready,

    async call(action, body) {
        const handler = await ready;
        const res = await handler.request({
            method: 'POST',
            url: `/api.php?action=${action}`,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return JSON.parse(res.text);
    },

    async png(payload) {
        const handler = await ready;
        const res = await handler.request({ method: 'GET', url: `/api.php?action=png&payload=${encodeURIComponent(payload)}` });
        if (res.httpStatusCode !== 200) throw new Error(res.text);
        return new Blob([res.bytes], { type: 'image/png' });
    },
};
