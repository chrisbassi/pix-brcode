<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode\Tests;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;
use ChrisBassi\PixBrCode\PixKey;
use ChrisBassi\PixBrCode\PixKeyType;
use PHPUnit\Framework\TestCase;

final class PixKeyTest extends TestCase
{
    public function testDetectsAndNormalizesEachType(): void
    {
        $cases = [
            ['Chris@Email.com', PixKeyType::Email, 'chris@email.com'],
            ['+55 (62) 99999-8888', PixKeyType::Phone, '+5562999998888'],
            ['123E4567E89B12D3A456426614174000', PixKeyType::Evp, '123e4567-e89b-12d3-a456-426614174000'],
            ['529.982.247-25', PixKeyType::Cpf, '52998224725'],
            ['11.222.333/0001-81', PixKeyType::Cnpj, '11222333000181'],
            ['12.ABC.345/01DE-35', PixKeyType::Cnpj, '12ABC34501DE35'],
        ];

        foreach ($cases as [$raw, $type, $normalized]) {
            $key = PixKey::from($raw);
            $this->assertSame($type, $key->type, $raw);
            $this->assertSame($normalized, $key->value, $raw);
        }
    }

    public function testAcceptsPhoneWithoutCountryCodeWhenTypeIsGiven(): void
    {
        $this->assertSame('+5562999998888', PixKey::from('62 99999-8888', PixKeyType::Phone)->value);
    }

    public function testRejectsInvalidCpf(): void
    {
        $this->expectException(InvalidPixException::class);

        PixKey::from('123.456.789-00');
    }

    public function testRejectsRepeatedDigitsCpf(): void
    {
        $this->assertFalse(PixKey::isValid('111.111.111-11'));
    }

    public function testRejectsInvalidCnpj(): void
    {
        $this->assertFalse(PixKey::isValid('11.222.333/0001-82'));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->assertFalse(PixKey::isValid('chris@', PixKeyType::Email));
    }

    public function testRejectsUnknownFormat(): void
    {
        $this->expectException(InvalidPixException::class);

        PixKey::from('isso nao e chave');
    }
}
