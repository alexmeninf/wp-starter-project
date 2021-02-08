<?php

namespace WPSynchro;

/**
 * Class for setting up the service controller
 *
 * @since 1.0.0
 */
class ServiceController
{

    private $map = array();
    private $singletons = array();

    public function add($identifier, $function)
    {
        $this->map[$identifier] = $function;
    }

    public function get($identifier)
    {
        if (isset($this->singletons[$identifier])) {
            return $this->singletons[$identifier];
        }
        return $this->map[$identifier]();
    }

    public function share($identifier, $function)
    {
        $this->singletons[$identifier] = $function();
    }

    public static function init()
    {

        global $wpsynchro_container;
        $wpsynchro_container = new ServiceController();

        /*
         *  InstallationFactory
         */
        $wpsynchro_container->share(
            'class.InstallationFactory',
            function () {
                return new \WPSynchro\InstallationFactory();
            }
        );

        /*
         *  Installation
         */
        $wpsynchro_container->add(
            'class.Installation',
            function () {
                return new \WPSynchro\Installation();
            }
        );

        /*
         *  Job
         */
        $wpsynchro_container->add(
            'class.Job',
            function () {
                return new \WPSynchro\Job();
            }
        );

        /*
         *  InitiateSync
         */
        $wpsynchro_container->add(
            'class.InitiateSync',
            function () {
                return new \WPSynchro\Initiate\InitiateSync();
            }
        );

        /*
         *  MasterdataSync
         */
        $wpsynchro_container->add(
            'class.MasterdataSync',
            function () {
                return new \WPSynchro\Masterdata\MasterdataSync();
            }
        );

        /*
         *  DatabaseBackup
         */
        $wpsynchro_container->add(
            'class.DatabaseBackup',
            function () {
                return new \WPSynchro\Database\DatabaseBackup();
            }
        );

        /*
         *  DatabaseSync
         */
        $wpsynchro_container->add(
            'class.DatabaseSync',
            function () {
                return new \WPSynchro\Database\DatabaseSync();
            }
        );

        /*
         *  DatabaseFinalize
         */
        $wpsynchro_container->add(
            'class.DatabaseFinalize',
            function () {
                return new \WPSynchro\Database\DatabaseFinalize();
            }
        );

        /*
         *  FilesSync
         */
        $wpsynchro_container->add(
            'class.FilesSync',
            function () {
                return new \WPSynchro\Files\FilesSync();
            }
        );

        /*
         *  PopulateListHandler
         */
        $wpsynchro_container->add(
            'class.PopulateListHandler',
            function () {
                return new \WPSynchro\Files\PopulateListHandler();
            }
        );

        /*
         *  PathHandler
         */
        $wpsynchro_container->add(
            'class.PathHandler',
            function () {
                return new \WPSynchro\Files\PathHandler();
            }
        );

        /*
         *  TransferFiles
         */
        $wpsynchro_container->add(
            'class.TransferFiles',
            function () {
                return new \WPSynchro\Files\TransferFiles();
            }
        );

        /*
         *  TransportHandler
         */
        $wpsynchro_container->add(
            'class.TransportHandler',
            function () {
                return new \WPSynchro\Files\TransportHandler();
            }
        );

        /*
         *  FinalizeFiles
         */
        $wpsynchro_container->add(
            'class.FinalizeFiles',
            function () {
                return new \WPSynchro\Files\FinalizeFiles();
            }
        );

        /*
         *  FinalizeSync
         */
        $wpsynchro_container->add(
            'class.FinalizeSync',
            function () {
                return new \WPSynchro\Finalize\FinalizeSync();
            }
        );

        /*
         *  Location
         */
        $wpsynchro_container->add(
            'class.Location',
            function () {
                return new \WPSynchro\Files\Location();
            }
        );

        /*
         *  SynchronizeController - Singleton
         */
        $wpsynchro_container->share(
            'class.SynchronizeController',
            function () {
                return new \WPSynchro\SynchronizeController();
            }
        );

        /*
         *  SynchronizeStatus
         */
        $wpsynchro_container->add(
            'class.SynchronizeStatus',
            function () {
                return new \WPSynchro\Status\SynchronizeStatus();
            }
        );

        /*
         *  CommonFunctions
         */
        $wpsynchro_container->share(
            'class.CommonFunctions',
            function () {
                return new \WPSynchro\CommonFunctions();
            }
        );

        /*
         *  DebugInformation
         */
        $wpsynchro_container->add(
            'class.DebugInformation',
            function () {
                return new \WPSynchro\Utilities\DebugInformation();
            }
        );

        /*
         *  Licensing 
         */
        $wpsynchro_container->add(
            'class.Licensing',
            function () {
                return new \WPSynchro\Licensing();
            }
        );

        /**
         *  Logger
         */
        $wpsynchro_container->share(
            'class.Logger',
            function () {

                $logpath = wp_upload_dir()['basedir'] . "/wpsynchro/";
                $logger = new \WPSynchro\Logger\FileLogger;
                $logger->setFilePath($logpath);

                return $logger;
            }
        );

        /**
         *  MetadataLog - for saving data on a sync run
         */
        $wpsynchro_container->share(
            'class.SyncMetadataLog',
            function () {
                return new \WPSynchro\Logger\SyncMetadataLog();
            }
        );

        /**
         *  SyncTimerList - Controls all the timers during sync
         */
        $wpsynchro_container->share(
            'class.SyncTimerList',
            function () {
                return new \WPSynchro\Utilities\SyncTimerList();
            }
        );

        /**
         *  Transfer - Get transfer object
         */
        $wpsynchro_container->add(
            'class.Transfer',
            function () {
                return new \WPSynchro\Transport\Transfer();
            }
        );

        /**
         *  RemoteTransfer - Get transfer object to move and receive data
         */
        $wpsynchro_container->add(
            'class.RemoteTransfer',
            function () {
                return new \WPSynchro\Transport\RemoteTransport();
            }
        );

        /**
         *  RemoteTransferResult - Result of remote transfer, to be used in code
         */
        $wpsynchro_container->add(
            'class.RemoteTransferResult',
            function () {
                return new \WPSynchro\Transport\RemoteTransportResult();
            }
        );

        /**
         *  ReturnResult - Return data from REST service (wrapper for Transfer object)
         */
        $wpsynchro_container->add(
            'class.ReturnResult',
            function () {
                return new \WPSynchro\Transport\ReturnResult();
            }
        );
    }
}
