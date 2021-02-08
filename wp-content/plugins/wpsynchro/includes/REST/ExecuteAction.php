<?php
namespace WPSynchro\REST;

use WPSynchro\Transport\TransferAccessKey;
use WPSynchro\Utilities\Actions\ClearCachesOnSuccess;
use WPSynchro\Utilities\Actions\ClearCurrentTransfer;

/**
 * Class for handling REST service "executeaction"
 * Call should already be verified by permissions callback
 * @since 1.6.0
 */
class ExecuteAction
{
    
    public function service($request)
    {
        
        global $wpdb;
        $result = new \stdClass();

        // Get transfer object, so we can get data
        global $wpsynchro_container;
        $transfer = $wpsynchro_container->get("class.Transfer");
        $transfer->setEncryptionKey(TransferAccessKey::getAccessKey());
        $transfer->populateFromString($request->get_body());
        $data = $transfer->getDataObject();

        if ( in_array("clearcaches", $data)) {
            (new ClearCachesOnSuccess())->doAction([]);
        }

        if ( in_array("cleartransfertoken", $data)) {
            (new ClearCurrentTransfer())->doAction([]);
        }

        // Return
        global $wpsynchro_container;
        $returnresult = $wpsynchro_container->get('class.ReturnResult');
        $returnresult->init();
        $returnresult->setDataObject($result);
        return $returnresult->echoDataFromRestAndExit();
    }

}
