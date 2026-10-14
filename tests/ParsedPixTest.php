<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode\Tests;

use ChrisBassi\PixBrCode\Exception\InvalidPixException;
use ChrisBassi\PixBrCode\Pix;
use ChrisBassi\PixBrCode\PixKeyType;
use PHPUnit\Framework\TestCase;

final class ParsedPixTest extends TestCase
{
    private const BACEN_SAMPLE = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

    public function testReadsTheBacenSample(): void
    {
        $parsed = Pix::parse(self::BACEN_SAMPLE);

        $this->assertTrue($parsed->isValid());
        $this->assertSame('123e4567-e12b-12d1-a456-426655440000', $parsed->key());
        $this->assertSame(PixKeyType::Evp, $parsed->keyType());
        $this->assertSame('Fulano de Tal', $parsed->merchantName());
        $this->assertSame('BRASILIA', $parsed->merchantCity());
        $this->assertSame('***', $parsed->txid());
        $this->assertNull($parsed->amount());
        $this->assertFalse($parsed->isDynamic());
    }

    public function testRoundTripsAGeneratedCode(): void
    {
        $payload = Pix::static()
            ->key('+5562999998888')
            ->merchant('Chris Bassi', 'Goiania')
            ->amount(99.99)
            ->txid('ABC123')
            ->description('Camiseta')
            ->postalCode('74000-000')
            ->payload();

        $parsed = Pix::parse($payload);

        $this->assertTrue($parsed->isValid());
        $this->assertSame('+5562999998888', $parsed->key());
        $this->assertSame(PixKeyType::Phone, $parsed->keyType());
        $this->assertSame(99.99, $parsed->amount());
        $this->assertSame('ABC123', $parsed->txid());
        $this->assertSame('Camiseta', $parsed->description());
        $this->assertSame('74000000', $parsed->postalCode());
    }

    public function testFlagsAWrongCrc(): void
    {
        $tampered = substr(self::BACEN_SAMPLE, 0, -4) . 'FFFF';
        $parsed = Pix::parse($tampered);

        $this->assertFalse($parsed->isValid());
        $this->assertSame('1D3D', $parsed->expectedCrc());
        $this->assertStringContainsString('CRC', $parsed->errors()[0]);
    }

    public function testFlagsATamperedValueThroughTheCrc(): void
    {
        $tampered = str_replace('Fulano de Tal', 'Fulano de Sal', self::BACEN_SAMPLE);

        $this->assertFalse(Pix::parse($tampered)->isValid());
    }

    public function testRecordsFieldOffsets(): void
    {
        $parsed = Pix::parse(self::BACEN_SAMPLE);
        $name = $parsed->field('59');

        $this->assertNotNull($name);
        $this->assertSame('5913Fulano de Tal', substr(self::BACEN_SAMPLE, $name->offset, $name->span()));
    }

    public function testIgnoresLineBreaksFromPasting(): void
    {
        $this->assertTrue(Pix::parse(" \n" . chunk_split(self::BACEN_SAMPLE, 40, "\n"))->isValid());
    }

    public function testThrowsOnBrokenStructure(): void
    {
        $this->expectException(InvalidPixException::class);

        Pix::parse('000201265');
    }
}
