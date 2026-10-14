<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;

/**
 * Uma chave Pix já normalizada e validada.
 *
 * A lib só confere o formato. Saber se a chave existe de verdade é papel do
 * DICT, o diretório de chaves do Banco Central.
 */
final class PixKey implements \Stringable
{
    private function __construct(
        public readonly string $value,
        public readonly PixKeyType $type,
    ) {
    }

    /**
     * Cria a chave detectando o tipo, ou validando contra o tipo informado.
     */
    public static function from(string $raw, ?PixKeyType $type = null): self
    {
        $raw = trim($raw);

        if ($raw === '') {
            throw new InvalidPixException('Informe uma chave Pix.');
        }

        $type ??= self::detect($raw);

        $value = match ($type) {
            PixKeyType::Email => self::email($raw),
            PixKeyType::Phone => self::phone($raw),
            PixKeyType::Evp => self::evp($raw),
            PixKeyType::Cpf => self::cpf($raw),
            PixKeyType::Cnpj => self::cnpj($raw),
        };

        return new self($value, $type);
    }

    public static function isValid(string $raw, ?PixKeyType $type = null): bool
    {
        try {
            self::from($raw, $type);

            return true;
        } catch (InvalidPixException) {
            return false;
        }
    }

    public static function detect(string $raw): PixKeyType
    {
        $raw = trim($raw);

        if (str_contains($raw, '@')) {
            return PixKeyType::Email;
        }

        if (str_starts_with($raw, '+')) {
            return PixKeyType::Phone;
        }

        if (preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $raw)) {
            return PixKeyType::Evp;
        }

        $compact = preg_replace('/[\s.\-\/]/', '', $raw) ?? '';

        if (preg_match('/^\d{11}$/', $compact)) {
            if (self::cpfIsValid($compact)) {
                return PixKeyType::Cpf;
            }

            throw new InvalidPixException(
                'Esses 11 dígitos não formam um CPF válido. Se for um telefone, use o formato +55 com DDD, por exemplo +5562999998888.'
            );
        }

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/i', $compact)) {
            return PixKeyType::Cnpj;
        }

        throw new InvalidPixException(
            'Não reconheci o tipo da chave. Use CPF, CNPJ, e-mail, telefone com +55 ou chave aleatória.'
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function email(string $raw): string
    {
        $email = strtolower($raw);

        if (strlen($email) > 77 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPixException('O e-mail informado não é válido ou passa de 77 caracteres.');
        }

        return $email;
    }

    private static function phone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (!str_starts_with(trim($raw), '+')) {
            $digits = '55' . $digits;
        }

        if (!preg_match('/^55[1-9]{2}9?\d{8}$/', $digits)) {
            throw new InvalidPixException(
                'O telefone deve ter código do país, DDD e número, por exemplo +5562999998888.'
            );
        }

        return '+' . $digits;
    }

    private static function evp(string $raw): string
    {
        $hex = strtolower(str_replace('-', '', $raw));

        if (!preg_match('/^[0-9a-f]{32}$/', $hex)) {
            throw new InvalidPixException('A chave aleatória deve ser um UUID, como 123e4567-e89b-12d3-a456-426614174000.');
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private static function cpf(string $raw): string
    {
        $digits = preg_replace('/[\s.\-]/', '', $raw) ?? '';

        if (!self::cpfIsValid($digits)) {
            throw new InvalidPixException('O CPF informado não é válido.');
        }

        return $digits;
    }

    private static function cnpj(string $raw): string
    {
        $value = strtoupper(preg_replace('/[\s.\-\/]/', '', $raw) ?? '');

        if (!self::cnpjIsValid($value)) {
            throw new InvalidPixException('O CNPJ informado não é válido.');
        }

        return $value;
    }

    private static function cpfIsValid(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $cpf[$i] * (($position + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aceita o CNPJ numérico e o alfanumérico (12 caracteres alfanuméricos e
     * 2 dígitos verificadores). No cálculo, cada caractere vale ord($c) - 48.
     */
    private static function cnpjIsValid(string $cnpj): bool
    {
        if (!preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];

        foreach ([12, 13] as $index => $position) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * $weights[$index][$i];
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ((int) $cnpj[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
