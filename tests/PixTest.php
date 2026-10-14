<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode\Tests;

use ChrisBassi\PixBrCode\Crc16;
use ChrisBassi\PixBrCode\Exception\InvalidPixException;
use ChrisBassi\PixBrCode\Pix;
use PHPUnit\Framework\TestCase;

final class PixTest extends TestCase
{
    /** Exemplo de Pix estático do manual do BR Code do Banco Central. */
    private const BACEN_SAMPLE = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

    public function testGeneratesTheBacenSampleByteForByte(): void
    {
        $payload = Pix::static()
            ->key('123e4567-e12b-12d1-a456-426655440000')
            ->merchant('Fulano de Tal', 'BRASILIA')
            ->payload();

        $this->assertSame(self::BACEN_SAMPLE, $payload);
    }

    public function testCrcMatchesTheBacenSample(): void
    {
        $this->assertSame('1D3D', Crc16::compute(substr(self::BACEN_SAMPLE, 0, -4)));
    }

    public function testIncludesAmountTxidAndDescription(): void
    {
        $payload = Pix::static()
            ->key('chris@email.com')
            ->merchant('Chris Bassi', 'Goiânia')
            ->amount(25.9)
            ->txid('PEDIDO123')
            ->description('Pedido 123')
            ->payload();

        $this->assertStringContainsString('540525.90', $payload);
        $this->assertStringContainsString('0509PEDIDO123', $payload);
        $this->assertStringContainsString('0210Pedido 123', $payload);
        $this->assertStringContainsString('6007Goiania', $payload);
        $this->assertTrue(Pix::parse($payload)->isValid());
    }

    public function testAcceptsBrazilianAmountFormat(): void
    {
        $payload = Pix::static()->key('chris@email.com')->merchant('Chris', 'Goiania')->amount('1.234,50')->payload();

        $this->assertSame(1234.5, Pix::parse($payload)->amount());
    }

    public function testOmitsAmountWhenOpen(): void
    {
        $payload = Pix::static()->key('chris@email.com')->merchant('Chris', 'Goiania')->amount(null)->payload();

        $this->assertNull(Pix::parse($payload)->amount());
    }

    public function testTruncatesNameAndCityToTheirLimits(): void
    {
        $parsed = Pix::parse(
            Pix::static()
                ->key('chris@email.com')
                ->merchant('Um Nome Muito Comprido Para Caber Aqui', 'Aparecida de Goiânia')
                ->payload()
        );

        $this->assertSame(25, strlen((string) $parsed->merchantName()));
        $this->assertSame('Aparecida de Go', $parsed->merchantCity());
    }

    public function testMarksSingleUse(): void
    {
        $payload = Pix::static()->key('chris@email.com')->merchant('Chris', 'Goiania')->singleUse()->payload();

        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertTrue(Pix::parse($payload)->isSingleUse());
    }

    public function testBuilderIsImmutable(): void
    {
        $base = Pix::static()->key('chris@email.com')->merchant('Chris', 'Goiania');
        $withAmount = $base->amount(10);

        $this->assertNotSame($base->payload(), $withAmount->payload());
    }

    public function testRejectsInvalidTxid(): void
    {
        $this->expectException(InvalidPixException::class);

        Pix::static()->txid('pedido 123!');
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(InvalidPixException::class);

        Pix::static()->amount(0);
    }

    public function testRequiresKey(): void
    {
        $this->expectException(InvalidPixException::class);

        Pix::static()->merchant('Chris', 'Goiania')->payload();
    }

    public function testRendersQrAsSvg(): void
    {
        $svg = Pix::static()->key('chris@email.com')->merchant('Chris', 'Goiania')->qrSvg();

        $this->assertStringContainsString('<svg', $svg);
    }
}
