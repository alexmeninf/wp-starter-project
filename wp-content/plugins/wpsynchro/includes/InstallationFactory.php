<?php

namespace WPSynchro;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Factory class for handling a "sync installation"
 * @since 1.0.0
 */

class InstallationFactory
{

    // Installations
    public $installations = array();
    // Is data loaded?
    private $loaded = false;

    /**
     * Function to return all installations
     * @since 1.0.0
     */
    public function getAllInstallations()
    {
        if (!$this->loaded) {
            $this->loadData();
        }
        return $this->installations;
    }

    /**
     * Function to retrieve a single installation by id
     * @since 1.0.0
     */
    public function retrieveInstallation($id)
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        foreach ($this->installations as $installation) {
            if ($installation->id == $id) {
                $installation->checkAndUpdateToPreset();
                return $installation;
            }
        }

        return false;
    }

    /**
     * Function to delete a single installation by id
     * @since 1.0.0
     */
    public function deleteInstallation($id)
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        // Find and delete it if exists
        foreach ($this->installations as $key => $installation) {
            if ($installation->id == $id) {
                unset($this->installations[$key]);
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Function to duplicate a single installation by id
     * @since 1.3.0
     */
    public function duplicateInstallation($id)
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        foreach ($this->installations as $key => $installation) {
            if ($installation->id == $id) {
                $new_installation = unserialize(serialize($installation));
                $new_installation->id = uniqid();
                $new_installation->name = $new_installation->name . " copy";
                $this->addInstallation($new_installation);
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Function to save installations
     * @since 1.0.0
     */
    public function save()
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        $savedata = array();
        foreach ($this->installations as $installation) {
            $savedata[] = (array) $installation;
        }

        update_option('wpsynchro_installations', $savedata, false);
    }

    /**
     * Function to load installation data from db
     * @since 1.0.0
     */
    private function loadData()
    {
        // Load data
        $installations_option = get_option('wpsynchro_installations', false);
        if ($installations_option !== false) {
            foreach ($installations_option as $inst) {
                $temp_installation = new Installation();
                foreach ($inst as $key => $value) {
                    $temp_installation->$key = $value;
                }
                $temp_installation->prepareGeneratedData();
                $this->installations[] = $temp_installation;
            }
        }
        $this->loaded = true;
    }

    /**
     * Function to add a installation
     * @since 1.0.0
     */
    public function addInstallation(Installation $inst)
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        // Check if it exist already
        foreach ($this->installations as $key => $installation) {
            if ($installation->id == $inst->id) {
                $this->installations[$key] = $inst;
                $this->save();
                return;
            }
        }
        $this->installations[] = $inst;
        $this->save();
    }

    /**
     * Function to start a installation (if not started)
     * @since 1.0.0
     */
    public function startInstallationSync($id, $jobid)
    {
        if (!$this->loaded) {
            $this->loadData();
        }

        // Check if exists
        $inst = null;
        foreach ($this->installations as $installation) {
            if ($installation->id == $id) {
                $inst = $installation;
                break;
            }
        }

        if ($inst == null) {
            return null;
        }

        // Create specific job for processing in db
        $job_identifier = 'wpsynchro_' . $id . '_' . $jobid;
        $job = get_option($job_identifier, false);
        if (!$job) {
            $job_arr = [];
            update_option($job_identifier, $job_arr, false);
        }

        return $jobid;
    }

    /**
     * Get a local installation object
     * @since 1.6.0
     */
    public function getLocalInstallation()
    {
        $inst = new Installation();
        $inst->site_url = get_home_url();
        $inst->access_key = TransferAccessKey::getAccessKey();
        $inst->verify_ssl = false;
        $inst->type = "local";

        // Check for basic auth in setup
        $basic_auth = get_option('wpsynchro_setup_basic_auth');
        if ($basic_auth && count($basic_auth) > 0) {
            $inst->connection_type = "basicauth";
            $inst->basic_auth_username = $basic_auth[0];
            $inst->basic_auth_password = $basic_auth[1];
        }

        return $inst;
    }
}
