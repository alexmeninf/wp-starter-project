<?php

namespace WPSynchro\Transport;

/**
 * Transfer access key
 * @since 1.6.0
 */
class TransferAccessKey
{

    /**
     * Return this installation access key
     * @since 1.0.0
     */
    public static function getAccessKey()
    {
        return get_option('wpsynchro_accesskey', "");
    }

    /**
     * Generate access key
     * @since 1.0.0
     */
    public static function generateAccesskey()
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        return $token;
    }
}
