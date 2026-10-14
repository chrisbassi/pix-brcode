<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

/**
 * CRC16-CCITT (polinômio 0x1021, valor inicial 0xFFFF), o checksum exigido
 * pelo BR Code no campo 63.
 */
final class Crc16
{
    public static function compute(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    /**
     * Calcula o CRC de um payload que já termina em "6304" (ID + tamanho do campo CRC).
     */
    public static function sign(string $payloadWithoutCrc): string
    {
        return $payloadWithoutCrc . self::compute($payloadWithoutCrc);
    }
}
