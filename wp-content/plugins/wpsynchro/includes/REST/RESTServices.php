<?php

namespace WPSynchro\REST;

use WPSynchro\Transport\TransferToken;

/**
 * Class for handling REST for WP Synchro
 *
 * @since 1.0.0
 */
class RESTServices
{
    /**
     * Setup the REST routes needed for WP Synchro
     *
     * @since 1.0.0
     */
    public function setup()
    {
        // Add "initiate" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/initiate/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\Initiate();
                        return $restservice->service($request);
                    },
                    'permission_callback' => '__return_true',
                ]
            );
            }
        );

        // Add "masterdata" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/masterdata/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\MasterData();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "backupdatabase" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/backupdatabase/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\DatabaseBackup();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "clientsyncdatabase" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/clientsyncdatabase/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\ClientSyncDatabase();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "populatefilelist" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/populatefilelist/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\PopulateFileList();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "populatefileliststatus" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/populatefileliststatus/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\PopulateFileListStatus();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "filetransfer" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/filetransfer/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\FileTransfer();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "getfiles" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/getfiles/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\GetFiles();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "filefinalize" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/filefinalize/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\FileFinalize();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "filesystem" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/filesystem/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\Filesystem();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function ($request) {
                        if ($this->permissionCheck($request)) {
                            return true;
                        } else {
                            return current_user_can('manage_options');
                        }
                    },
                ]
            );
            }
        );

        // Add "executeaction" REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/executeaction/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\ExecuteAction();
                        return $restservice->service($request);
                    },
                    'permission_callback' => [$this, 'permissionCheck'],
                ]
            );
            }
        );

        // Add "synchronize"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/synchronize/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\Synchronize();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function ($request) {
                        if ($this->permissionCheck($request)) {
                            return true;
                        } else {
                            return current_user_can('manage_options');
                        }
                    },
                ]
            );
            }
        );

        // Add "status"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/status/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\Status();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function ($request) {
                        if ($this->permissionCheck($request)) {
                            return true;
                        } else {
                            return current_user_can('manage_options');
                        }
                    },
                ]
            );
            }
        );

        // Add "downloadlog"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/downloadlog/',
                [
                    'methods' => 'GET',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\DownloadLog();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ]
            );
            }
        );

        // Add "healthcheck"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/healthcheck/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\HealthCheck();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ]
            );
            }
        );

        // Add "timeoutcheck"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/timeoutcheck/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\TimeoutCheck();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ]
            );
            }
        );

        // Add "checkinstallation"  REST endpoint
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                'wpsynchro/v1',
                '/installation/verify/',
                [
                    'methods' => 'POST',
                    'callback' => function ($request) {
                        $restservice = new \WPSynchro\REST\VerifyInstallation();
                        return $restservice->service($request);
                    },
                    'permission_callback' => function () {
                        return current_user_can('manage_options');
                    },
                ]
            );
            }
        );
    }

    /**
     *  Validates access to WP Synchro REST services
     */
    public function permissionCheck($request)
    {
        $token = $request->get_param('token');
        if ($token == null || strlen($token) < 20) {
            return false;
        }
        $token = trim($token);

        // Check if it is a transfer token
        return TransferToken::validateTransferToken($token);
    }
}
