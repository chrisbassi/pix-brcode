<?php

declare(strict_types=1);

use ChrisBassi\PixBrCode\Exception\InvalidPixException;
use ChrisBassi\PixBrCode\Pix;
use ChrisBassi\PixBrCode\PixKey;
use ChrisBassi\PixBrCode\QrCode;

require __DIR__ . '/../vendor/autoload.php';

$action = $_GET['action'] ?? '';

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
}

try {
    switch ($action) {
        case 'generate':
            $in = input();
            $errors = [];
            $pix = Pix::static();

            // Cada etapa vira um erro no campo certo do formulário.
            $steps = [
                'key' => fn (Pix $p) => $p->key((string) ($in['key'] ?? '')),
                'merchant' => fn (Pix $p) => $p->merchant((string) ($in['name'] ?? ''), (string) ($in['city'] ?? '')),
                'amount' => fn (Pix $p) => $p->amount($in['amount'] ?? null),
                'txid' => fn (Pix $p) => $p->txid($in['txid'] ?? null),
                'description' => fn (Pix $p) => $p->description($in['description'] ?? null),
                'singleUse' => fn (Pix $p) => $p->singleUse((bool) ($in['singleUse'] ?? false)),
            ];

            foreach ($steps as $field => $step) {
                try {
                    $pix = $step($pix);
                } catch (InvalidPixException $e) {
                    $errors[$field] = $e->getMessage();
                }
            }

            if ($errors !== []) {
                respond(['ok' => false, 'errors' => $errors, 'keyType' => keyTypeOf($in['key'] ?? '')], 422);
            }

            $payload = $pix->payload();

            respond([
                'ok' => true,
                'payload' => $payload,
                'svg' => inlineSvg($pix->qrSvg(320, 1)),
                'keyType' => $pix->getKey()?->type->label(),
                'parsed' => Pix::parse($payload),
            ]);

        case 'parse':
            $payload = (string) (input()['payload'] ?? '');
            $parsed = Pix::parse($payload);

            respond([
                'ok' => true,
                'parsed' => $parsed,
                'svg' => $parsed->isValid() ? inlineSvg((new QrCode($parsed->payload))->svg(320, 1)) : null,
            ]);

        case 'png':
            $parsed = Pix::parse((string) ($_GET['payload'] ?? ''));

            if (!$parsed->isValid()) {
                respond(['ok' => false, 'error' => 'Código inválido.'], 422);
            }

            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="pix.png"');
            echo (new QrCode($parsed->payload))->png(800, 2);
            exit;

        default:
            respond(['ok' => false, 'error' => 'Ação desconhecida.'], 404);
    }
} catch (InvalidPixException $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 422);
}

function keyTypeOf(mixed $raw): ?string
{
    try {
        return PixKey::from((string) $raw)->type->label();
    } catch (InvalidPixException) {
        return null;
    }
}

/** Remove a declaração XML para o SVG poder entrar direto no HTML. */
function inlineSvg(string $svg): string
{
    return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
}
