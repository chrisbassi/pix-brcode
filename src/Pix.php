<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;

/**
 * Monta um BR Code de Pix estático (o "copia e cola").
 *
 *     $pix = Pix::static()
 *         ->key('chris@email.com')
 *         ->merchant('Chris Bassi', 'Goiania')
 *         ->amount(25.90)
 *         ->txid('PEDIDO123');
 *
 *     echo $pix->payload();
 */
final class Pix implements \Stringable
{
    public const GUI = 'br.gov.bcb.pix';

    private ?PixKey $key = null;
    private string $merchantName = '';
    private string $merchantCity = '';
    private ?string $amount = null;
    private string $txid = '***';
    private ?string $description = null;
    private ?string $postalCode = null;
    private bool $singleUse = false;

    private function __construct()
    {
    }

    public static function static(): self
    {
        return new self();
    }

    /**
     * Lê um código Pix existente e devolve seus campos.
     */
    public static function parse(string $payload): ParsedPix
    {
        return ParsedPix::fromPayload($payload);
    }

    public function key(string|PixKey $key, ?PixKeyType $type = null): self
    {
        $clone = clone $this;
        $clone->key = $key instanceof PixKey ? $key : PixKey::from($key, $type);

        return $clone;
    }

    public function merchant(string $name, string $city): self
    {
        $clone = clone $this;
        $clone->merchantName = Text::clean($name, 25);
        $clone->merchantCity = Text::clean($city, 15);

        if ($clone->merchantName === '') {
            throw new InvalidPixException('Informe o nome de quem vai receber.');
        }

        if ($clone->merchantCity === '') {
            throw new InvalidPixException('Informe a cidade de quem vai receber.');
        }

        return $clone;
    }

    /**
     * Valor em reais. Use null para deixar o valor em aberto: quem paga digita.
     */
    public function amount(float|int|string|null $amount): self
    {
        $clone = clone $this;

        if ($amount === null || $amount === '') {
            $clone->amount = null;

            return $clone;
        }

        // Aceita "1.234,56" e "1234.56".
        if (is_string($amount) && str_contains($amount, ',')) {
            $amount = str_replace(['.', ','], ['', '.'], $amount);
        }

        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidPixException('O valor precisa ser um número maior que zero.');
        }

        $formatted = number_format((float) $amount, 2, '.', '');

        if (strlen($formatted) > 13) {
            throw new InvalidPixException('O valor passa do limite de 13 caracteres do BR Code.');
        }

        $clone->amount = $formatted;

        return $clone;
    }

    /**
     * Identificador da transação: até 25 letras e números. Sem txid, o código usa "***".
     */
    public function txid(?string $txid): self
    {
        $clone = clone $this;
        $txid = trim((string) $txid);

        if ($txid === '' || $txid === '***') {
            $clone->txid = '***';

            return $clone;
        }

        if (!preg_match('/^[A-Za-z0-9]{1,25}$/', $txid)) {
            throw new InvalidPixException('O txid aceita só letras e números, sem espaços, com até 25 caracteres.');
        }

        $clone->txid = $txid;

        return $clone;
    }

    /**
     * Mensagem que aparece para quem paga, quando o app do banco exibe.
     */
    public function description(?string $description): self
    {
        $clone = clone $this;
        $description = $description === null ? '' : Text::clean($description, 72);
        $clone->description = $description === '' ? null : $description;

        return $clone;
    }

    public function postalCode(?string $cep): self
    {
        $clone = clone $this;
        $digits = preg_replace('/\D/', '', (string) $cep) ?? '';

        if ($digits !== '' && strlen($digits) !== 8) {
            throw new InvalidPixException('O CEP precisa ter 8 dígitos.');
        }

        $clone->postalCode = $digits === '' ? null : $digits;

        return $clone;
    }

    /**
     * Marca o código como de uso único (método de iniciação 12).
     */
    public function singleUse(bool $singleUse = true): self
    {
        $clone = clone $this;
        $clone->singleUse = $singleUse;

        return $clone;
    }

    public function payload(): string
    {
        if ($this->key === null) {
            throw new InvalidPixException('Informe a chave Pix com ->key().');
        }

        if ($this->merchantName === '' || $this->merchantCity === '') {
            throw new InvalidPixException('Informe o nome e a cidade com ->merchant().');
        }

        $account = Tlv::encode('00', self::GUI) . Tlv::encode('01', $this->key->value);

        if ($this->description !== null) {
            $room = 99 - strlen($account) - 4;
            if ($room < 1) {
                throw new InvalidPixException('A chave ocupa todo o espaço e não sobra lugar para a descrição.');
            }
            $account .= Tlv::encode('02', rtrim(substr($this->description, 0, $room)));
        }

        $payload = Tlv::encode('00', '01');

        if ($this->singleUse) {
            $payload .= Tlv::encode('01', '12');
        }

        $payload .= Tlv::encode('26', $account)
            . Tlv::encode('52', '0000')
            . Tlv::encode('53', '986');

        if ($this->amount !== null) {
            $payload .= Tlv::encode('54', $this->amount);
        }

        $payload .= Tlv::encode('58', 'BR')
            . Tlv::encode('59', $this->merchantName)
            . Tlv::encode('60', $this->merchantCity);

        if ($this->postalCode !== null) {
            $payload .= Tlv::encode('61', $this->postalCode);
        }

        $payload .= Tlv::encode('62', Tlv::encode('05', $this->txid));

        return Crc16::sign($payload . '6304');
    }

    public function qr(): QrCode
    {
        return new QrCode($this->payload());
    }

    public function qrSvg(int $size = 320, int $margin = 2): string
    {
        return $this->qr()->svg($size, $margin);
    }

    public function qrPng(int $size = 320, int $margin = 2): string
    {
        return $this->qr()->png($size, $margin);
    }

    public function qrDataUri(int $size = 320, int $margin = 2): string
    {
        return $this->qr()->dataUri($size, $margin);
    }

    public function getKey(): ?PixKey
    {
        return $this->key;
    }

    public function __toString(): string
    {
        return $this->payload();
    }
}
