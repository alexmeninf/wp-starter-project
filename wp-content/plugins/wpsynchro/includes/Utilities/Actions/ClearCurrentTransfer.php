<?php
namespace WPSynchro\Utilities\Actions;

use WPSynchro\Utilities\Actions\Action;
use WPSynchro\Transport\TransferToken;

/**
 * Action: Clear current transfer - To block further requests
 * @since 1.6.0
 */
class ClearCurrentTransfer implements Action
{

    /**
     * Initialize
     * @since 1.6.0
     */
    public function init()
    {
    }

    /**
     * Execute action
     * @since 1.6.0
     */
    public function doAction($params)
    {
        TransferToken::deleteTransferToken();
    }
}
