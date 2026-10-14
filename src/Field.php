<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

/**
 * Um campo decodificado do BR Code, com sua posição na string original.
 */
final class Field implements \JsonSerializable
{
    private const ROOT_NAMES = [
        '00' => 'Formato do payload',
        '01' => 'Método de iniciação',
        '26' => 'Dados da conta Pix',
        '52' => 'Categoria do comerciante (MCC)',
        '53' => 'Moeda',
        '54' => 'Valor',
        '58' => 'País',
        '59' => 'Nome do recebedor',
        '60' => 'Cidade',
        '61' => 'CEP',
        '62' => 'Dados adicionais',
        '63' => 'CRC16',
        '80' => 'Template não reservado',
    ];

    private const PIX_NAMES = [
        '00' => 'Identificador (GUI)',
        '01' => 'Chave Pix',
        '02' => 'Descrição',
        '25' => 'URL do Pix dinâmico',
    ];

    private const ADDITIONAL_NAMES = [
        '05' => 'Identificador da transação (txid)',
    ];

    /**
     * @param list<Field> $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $value,
        public readonly int $offset,
        public readonly ?string $parentId = null,
        public readonly array $children = [],
    ) {
    }

    public static function isTemplate(string $id, ?string $parentId): bool
    {
        if ($parentId !== null) {
            return false;
        }

        $n = (int) $id;

        return ($n >= 26 && $n <= 51) || $n === 62 || ($n >= 80 && $n <= 99);
    }

    public function name(): string
    {
        return match (true) {
            $this->parentId === null => self::ROOT_NAMES[$this->id]
                ?? ((int) $this->id >= 26 && (int) $this->id <= 51 ? 'Dados da conta' : 'Campo ' . $this->id),
            $this->parentId === '62' => self::ADDITIONAL_NAMES[$this->id] ?? 'Campo ' . $this->id,
            default => self::PIX_NAMES[$this->id] ?? 'Campo ' . $this->id,
        };
    }

    public function length(): int
    {
        return strlen($this->value);
    }

    /** Tamanho total do campo na string, incluindo ID e tamanho. */
    public function span(): int
    {
        return 4 + $this->length();
    }

    public function child(string $id): ?Field
    {
        foreach ($this->children as $child) {
            if ($child->id === $id) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name(),
            'length' => $this->length(),
            'value' => $this->value,
            'offset' => $this->offset,
            'span' => $this->span(),
            'children' => $this->children,
        ];
    }
}
