<?php
namespace WPSynchro\Utilities\Actions;

/**
 * Interface for actions
 * @since 1.6.0
 */
interface Action
{
    public function init();
    public function doAction($params);
}
