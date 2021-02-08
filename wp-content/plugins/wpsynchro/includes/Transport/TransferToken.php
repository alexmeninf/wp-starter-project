<?php

namespace WPSynchro\Transport;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Transfer token
 * @since 1.6.0
 */
class TransferToken
{
    const TOKEN_ALGORITM = "sha256";
    const TOKEN_OPTION_NAME = "wpsynchro_current_transfer";

    /**
     * Get transfer token based
     * @since 1.0.0
     */
    public static function getTransferToken($accesskey, $jobtoken)
    {
        return hash(self::TOKEN_ALGORITM, $accesskey . $jobtoken);
    }

    /**
     * Validate transfer token
     * @since 1.0.0
     */
    public static function validateTransferToken($token_to_validate)
    {
        $jobtoken = "";

        // Check if is valid transfer token
        $current_transfer = get_option(self::TOKEN_OPTION_NAME, null);

        if (is_object($current_transfer)) {
            // Transfer exist, so check if it has activity or old
            if ($current_transfer->last_activity > (time() - $current_transfer->lifetime)) {
                $jobtoken = $current_transfer->token;
                // update last_activity
                $current_transfer->last_activity = time();
                update_option(self::TOKEN_OPTION_NAME, $current_transfer, false);
            } else {
                // Too old
                return false;
            }
        } else {
            // Does not exist
            return false;
        }

        $expected_transfer_token = self::getTransferToken(TransferAccessKey::getAccessKey(), $jobtoken);
        if (hash_equals($expected_transfer_token, $token_to_validate)) {
            return true;
        }

        return false;
    }

    /**
     *  Set new token
     *  @since 1.6.1
     */
    public static function setNewToken($lifespan)
    {
        $transfer = new \stdClass();
        $transfer->token = hash('sha256', openssl_random_pseudo_bytes(30));
        $transfer->last_activity = time();
        $transfer->lifetime = $lifespan;
        update_option(self::TOKEN_OPTION_NAME, $transfer, false);
        return $transfer->token;
    }

    /**
     * Delete transfer token
     * @since 1.6.0
     */
    public static function deleteTransferToken()
    {
        delete_option(self::TOKEN_OPTION_NAME);
    }
}
