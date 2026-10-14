<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;

/**
 * Resultado da leitura de um BR Code: os campos decodificados e o que está errado com eles.
 */
final class ParsedPix implements \JsonSerializable
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param list<Field> $fields
     */
    private function __construct(
        public readonly string $payload,
        private readonly array $fields,
    ) {
        $this->validate();
    }

    /**
     * @throws InvalidPixException quando a estrutura TLV está quebrada e não dá para ler os campos
     */
    public static function fromPayload(string $payload): self
    {
        $payload = trim(preg_replace('/[\r\n\t]+/', '', $payload) ?? '');

        if ($payload === '') {
            throw new InvalidPixException('Cole um código Pix para ler.');
        }

        return new self($payload, Tlv::decode($payload));
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $id): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->id === $id) {
                return $field;
            }
        }

        return null;
    }

    public function key(): ?string
    {
        return $this->pixAccount()?->child('01')?->value;
    }

    public function keyType(): ?PixKeyType
    {
        $key = $this->key();

        if ($key === null) {
            return null;
        }

        try {
            return PixKey::detect($key);
        } catch (InvalidPixException) {
            return null;
        }
    }

    public function description(): ?string
    {
        return $this->pixAccount()?->child('02')?->value;
    }

    /** URL de cobrança, presente só no Pix dinâmico. */
    public function url(): ?string
    {
        return $this->pixAccount()?->child('25')?->value;
    }

    public function isDynamic(): bool
    {
        return $this->url() !== null;
    }

    public function isSingleUse(): bool
    {
        return $this->field('01')?->value === '12';
    }

    public function merchantName(): ?string
    {
        return $this->field('59')?->value;
    }

    public function merchantCity(): ?string
    {
        return $this->field('60')?->value;
    }

    public function postalCode(): ?string
    {
        return $this->field('61')?->value;
    }

    public function amount(): ?float
    {
        $value = $this->field('54')?->value;

        return $value === null ? null : (float) $value;
    }

    public function txid(): ?string
    {
        return $this->field('62')?->child('05')?->value;
    }

    public function crc(): ?string
    {
        return $this->field('63')?->value;
    }

    public function expectedCrc(): string
    {
        $crcField = $this->field('63');
        $body = $crcField === null
            ? $this->payload . '6304'
            : substr($this->payload, 0, $crcField->offset + 4);

        return Crc16::compute($body);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $type = $this->keyType();

        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'key' => $this->key(),
            'keyType' => $type?->value,
            'keyTypeLabel' => $type?->label(),
            'merchantName' => $this->merchantName(),
            'merchantCity' => $this->merchantCity(),
            'amount' => $this->amount(),
            'txid' => $this->txid(),
            'description' => $this->description(),
            'dynamic' => $this->isDynamic(),
            'url' => $this->url(),
            'crc' => $this->crc(),
            'expectedCrc' => $this->expectedCrc(),
            'fields' => $this->fields,
        ];
    }

    private function pixAccount(): ?Field
    {
        foreach ($this->fields as $field) {
            $n = (int) $field->id;
            if ($n >= 26 && $n <= 51 && strcasecmp((string) $field->child('00')?->value, Pix::GUI) === 0) {
                return $field;
            }
        }

        return null;
    }

    private function validate(): void
    {
        if ($this->fields === [] || $this->fields[0]->id !== '00' || $this->fields[0]->value !== '01') {
            $this->errors[] = 'O código deve começar pelo campo 00 com valor 01.';
        }

        $last = $this->fields[array_key_last($this->fields)] ?? null;

        if ($last === null || $last->id !== '63') {
            $this->errors[] = 'O último campo deve ser o CRC (63).';
        } elseif (strtoupper($last->value) !== $this->expectedCrc()) {
            $this->errors[] = sprintf(
                'O CRC não confere: o código traz %s, mas o calculado é %s. Algum caractere foi alterado.',
                $last->value,
                $this->expectedCrc()
            );
        }

        $account = $this->pixAccount();

        if ($account === null) {
            $this->errors[] = 'Não encontrei os dados do Pix (identificador br.gov.bcb.pix).';
        } elseif ($account->child('01') === null && $account->child('25') === null) {
            $this->errors[] = 'Os dados do Pix não trazem chave (01) nem URL (25).';
        }

        $required = ['52' => null, '53' => '986', '58' => 'BR', '59' => null, '60' => null];

        foreach ($required as $id => $expected) {
            $id = (string) $id;
            $field = $this->field($id);
            if ($field === null) {
                $this->errors[] = sprintf('Falta o campo obrigatório %s (%s).', $id, (new Field($id, '', 0))->name());
            } elseif ($expected !== null && $field->value !== $expected) {
                $this->errors[] = sprintf('O campo %s deveria ser %s, mas é %s.', $id, $expected, $field->value);
            }
        }

        $amount = $this->field('54')?->value;
        if ($amount !== null && !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            $this->errors[] = sprintf('O valor "%s" não está no formato 0.00.', $amount);
        }
    }
}
