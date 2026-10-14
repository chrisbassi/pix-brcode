<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;

/**
 * Codifica e decodifica campos no formato TLV do EMV: ID (2), Tamanho (2), Valor.
 */
final class Tlv
{
    public static function encode(string $id, string $value): string
    {
        $length = strlen($value);

        if ($length > 99) {
            throw new InvalidPixException(sprintf(
                'O campo %s tem %d caracteres; o máximo permitido é 99.',
                $id,
                $length
            ));
        }

        return $id . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
    }

    /**
     * Lê uma sequência TLV e devolve os campos com suas posições na string original.
     *
     * @return list<Field>
     */
    public static function decode(string $data, int $offset = 0, ?string $parentId = null): array
    {
        $fields = [];
        $cursor = 0;
        $total = strlen($data);

        while ($cursor < $total) {
            if ($cursor + 4 > $total) {
                throw new InvalidPixException(sprintf(
                    'O código termina no meio de um campo, na posição %d.',
                    $offset + $cursor
                ));
            }

            $id = substr($data, $cursor, 2);
            $rawLength = substr($data, $cursor + 2, 2);

            if (!ctype_digit($id) || !ctype_digit($rawLength)) {
                throw new InvalidPixException(sprintf(
                    'Esperava um ID e um tamanho numéricos na posição %d, mas encontrei "%s".',
                    $offset + $cursor,
                    $id . $rawLength
                ));
            }

            $length = (int) $rawLength;
            $value = substr($data, $cursor + 4, $length);

            if (strlen($value) !== $length) {
                throw new InvalidPixException(sprintf(
                    'O campo %s diz ter %d caracteres, mas só restam %d.',
                    $id,
                    $length,
                    strlen($value)
                ));
            }

            $children = [];
            if (Field::isTemplate($id, $parentId)) {
                $children = self::decode($value, $offset + $cursor + 4, $id);
            }

            $fields[] = new Field(
                id: $id,
                value: $value,
                offset: $offset + $cursor,
                parentId: $parentId,
                children: $children,
            );

            $cursor += 4 + $length;
        }

        return $fields;
    }
}
