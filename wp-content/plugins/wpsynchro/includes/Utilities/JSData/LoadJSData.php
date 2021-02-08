<?php

/**
 * Class for providing data for JS
 * @since 1.6.0
 */

namespace WPSynchro\Utilities\JSData;


class LoadJSData
{

    /**
     *  Load the standard JS data that we use one multiple pages
     */
    public function load()
    {
        (new HealthCheckData())->load();
        (new PageHeaderData())->load();
    }
}
