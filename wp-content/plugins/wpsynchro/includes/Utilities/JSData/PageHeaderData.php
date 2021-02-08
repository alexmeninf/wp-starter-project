<?php

/**
 * Class for providing data for page headers
 * @since 1.6.0
 */

namespace WPSynchro\Utilities\JSData;

use WPSynchro\CommonFunctions;

class PageHeaderData
{

    /**
     *  Load the JS data for page headers
     */
    public function load()
    {
        $commonfunctions = new CommonFunctions();

        $jsdata = [
            "isPro" => $commonfunctions::isPremiumVersion(),
            "pageTitleImg" => $commonfunctions->getAssetUrl("icon.png"),
            "version" => WPSYNCHRO_VERSION,
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_page_header', $jsdata);
    }
}
