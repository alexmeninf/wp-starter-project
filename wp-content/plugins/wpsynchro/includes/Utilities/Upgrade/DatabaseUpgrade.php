<?php

namespace WPSynchro\Utilities\Upgrade;

/**
 * Handle database upgrades
 * @since 1.6.0
 */
class DatabaseUpgrade
{

    /**
     *  Check WP Synchro database version and compare with current
     *  @since 1.0.3
     */
    public static function checkDBVersion()
    {
        $dbversion = get_option('wpsynchro_dbversion');

        // If not set yet, just set it and continue with life
        if (!$dbversion || $dbversion == "") {
            $dbversion = 0;
        }

        // Check if it is same as current
        if ($dbversion == WPSYNCHRO_DB_VERSION) {
            // Puuurfect, all good, so return
            return;
        } else {
            // Database is different than current version
            if ($dbversion > WPSYNCHRO_DB_VERSION) {
                // Its newer? :|
                return;
            } else {
                // Its older, so lets upgrade
                self::handleDBUpgrade($dbversion);
            }
        }
    }

    /**
     *  Handle upgrading of DB versions
     *  @since 1.0.3
     */
    public static function handleDBUpgrade($current_version)
    {
        if ($current_version > WPSYNCHRO_DB_VERSION) {
            return false;
        }

        // Version 1 - First DB version, no upgrades needed
        if ($current_version < 1) {
            // nothing to do for first version
        }

        // Version 1 > 2
        if ($current_version < 2) {
            // Enable MU Plugin by default
            update_option('wpsynchro_muplugin_enabled', "yes", true);
        }

        // Version 2 > 3
        if ($current_version < 3) {
            // Update installations with the new preset setting
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
            $inst_factory->getAllInstallations();
            foreach ($inst_factory->installations as &$installation) {
                $installation->sync_preset = 'none';
                $installation->db_make_backup = false;
                $installation->searchreplaces = array();
            }
            $inst_factory->save();
        }

        // Version 3 > 4
        if ($current_version < 4) {
            // Update installations with the new table prefix setting
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
            $inst_factory->getAllInstallations();
            foreach ($inst_factory->installations as &$installation) {
                $installation->db_table_prefix_change = true;
            }
            $inst_factory->save();
        }

        // Version 4 > 5
        if ($current_version < 5) {
            // Clear file population object from db, as it has been changed
            delete_option("wpsynchro_filepopulation_current");
            // Remove IP security option, as it is removed in 1.6.0
            delete_option("wpsynchro_ip_security_enabled");
            // Set all installations as "direct" connections
            global $wpsynchro_container;
            $inst_factory = $wpsynchro_container->get("class.InstallationFactory");
            $inst_factory->getAllInstallations();
            foreach ($inst_factory->installations as &$installation) {
                $installation->connection_type = "direct";
            }
            $inst_factory->save();
        }

        // Version 5 > 6
        if ($current_version < 6) {
            delete_option("wpsynchro_debuglogging_enabled");
        }

        // Set to the db version for this release
        update_option('wpsynchro_dbversion', WPSYNCHRO_DB_VERSION, true);
        return true;
    }
}
