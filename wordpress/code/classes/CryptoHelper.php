<?php

class CryptoHelper
{
    private static $secretKey = '5780f52d9afad0e7a185ef5fd176925af7ce58529601ed0cea97d063280213ad'; // Should be a 32 bytes long key for AES-256-CBC
    private static $iv = '42df39c193185942461e6016feee73cd'; // Should be 16 bytes long

    // Encrypts the given plaintext
    public static function encrypt($plaintext)
    {
        $key = hash('sha256', self::$secretKey, true); // Ensure the key is correctly formatted
        $iv = substr(hash('sha256', self::$iv), 0, 16); // Ensure the IV is correctly formatted
        $encrypted = openssl_encrypt($plaintext, "AES-256-CBC", $key, 0, $iv);
        return base64_encode($encrypted); // Encode the encrypted data in base64 to ensure string representation
    }

    // Decrypts the given ciphertext
    public static function decrypt($ciphertext)
    {
        $key = hash('sha256', self::$secretKey, true); // Ensure the key is correctly formatted
        $iv = substr(hash('sha256', self::$iv), 0, 16); // Ensure the IV is correctly formatted
        $original_plaintext = openssl_decrypt(base64_decode($ciphertext), "AES-256-CBC", $key, 0, $iv);
        return $original_plaintext;
    }
}