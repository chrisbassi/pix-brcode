<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

enum PixKeyType: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Email = 'email';
    case Phone = 'phone';
    case Evp = 'evp';

    public function label(): string
    {
        return match ($this) {
            self::Cpf => 'CPF',
            self::Cnpj => 'CNPJ',
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::Evp => 'Chave aleatória',
        };
    }
}
