<?php

namespace App\Libraries;

/**
 * Custom Google 2FA helper for TOTP verification.
 */
class Google2FA
{
    private static $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new 2FA secret.
     */
    public static function generateSecret($length = 16)
    {
        $secret = '';
        $alphabet = self::$base32Alphabet;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify the provided OTP code against the secret.
     */
    public static function verifyKey($secret, $key, $window = 1)
    {
        $timestamp = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (self::calculateCode($secret, $timestamp + $i) == $key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate 2FA code for a specific timestamp.
     */
    private static function calculateCode($secret, $timestamp)
    {
        $binSecret = self::base32Decode($secret);
        $time = pack('N', 0) . pack('N', $timestamp);
        $hash = hash_hmac('sha1', $time, $binSecret, true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode base32 string.
     */
    private static function base32Decode($base32)
    {
        $base32 = strtoupper($base32);
        if (!preg_match('/^[A-Z2-7]+$/', $base32)) {
            return false;
        }
        $data = '';
        $n = 0;
        $j = 0;
        foreach (str_split($base32) as $char) {
            $n = ($n << 5) | strpos(self::$base32Alphabet, $char);
            $j += 5;
            if ($j >= 8) {
                $j -= 8;
                $data .= chr(($n >> $j) & 0xff);
            }
        }
        return $data;
    }

    /**
     * Generate QR code text (otpauth URL).
     */
    public static function getQRCodeText($userEmail, $secret, $issuer = 'Gondal Fulbe')
    {
        return "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($userEmail) . "?secret=" . $secret . "&issuer=" . rawurlencode($issuer);
    }
}
