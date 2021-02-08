<?php

namespace WPSynchro\Pages;

/**
 * Class for handling what to show when adding or editing a installation in wp-admin
 * @since 1.0.0
 */
class AdminAddEdit
{
    public static function render()
    {
        $instance = new self;
        // Handle post
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $instance->handlePOST();
        }
        $instance->handleGET();
    }

    private function handleGET()
    {
        // Check php/wp/mysql versions
        global $wpsynchro_container;
        $commonfunctions = $wpsynchro_container->get('class.CommonFunctions');
        $compat_errors = $commonfunctions->checkEnvCompatability();

        // Set the id
        if (isset($_REQUEST['syncid'])) {
            $id = sanitize_text_field($_REQUEST['syncid']);
        } else {
            $id = '';
        }

        // Get the data
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        $installation = $inst_factory->retrieveInstallation($id);

        if ($installation == false) {
            $installation = $wpsynchro_container->get('class.Installation');
        }

        // Is PRO version
        $is_pro = $commonfunctions::isPremiumVersion();

        // Localize the script with data
        $adminjsdata = [
            'instance' => $installation,
            'rest_root' => esc_url_raw(rest_url()),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'is_pro' => $is_pro,
            'text_error_gettoken' => __('Could not get token from remote server - Is WP Synchro installed and activated?', 'wpsynchro'),
            'text_error_response' => __('Got a response from remote site, but did not get correct response - Check access key and website url', 'wpsynchro'),
            'text_error_request' => __('No proper response from remote server - Check that website and access key is correct and WP Synchro is activated', 'wpsynchro'),
            'text_valid_endpoint_error_no_transfer_token' => __('No proper transfer token to use for safe communication - Try with another browser. Eg. newest Chrome.', 'wpsynchro'),
            'text_valid_endpoint_could_not_connect' => __('Could not connect to remote service - Check access key, website url and WP Synchro is activated', 'wpsynchro'),
            'text_valid_endpoint_http_error_debug' => __('Debug information - HTTP code: {0} - Response: {1}', 'wpsynchro'),
            'text_get_dbtables_error' => __('Could not grab the database tables names from remote', 'wpsynchro'),
            'text_get_filedetails_error' => __('Could not grab the file data from remote - It may be caused by different versions of WP Synchro', 'wpsynchro'),
            'text_validate_name_error' => __('Please choose a name for this installation', 'wpsynchro'),
            'text_validate_endpoint_error' => __('Website or access key is not valid', 'wpsynchro'),
            'text_validate_endpoint_compat_wp_in_own_dir_diff' => __('One of the sites seem to be using a non-standard location for WordPress core compared with the web root. This needs to be the same on both ends if synchronization also includes files. If you are just synchronizing database, you can ignore this warning. Source web root was: {0} and source WP dir: {1}. Target web root was {2} and target WP dir: {3}.', 'wpsynchro'),
            'text_validate_endpoint_different_plugin_versions' => __('Sites are using different versions of WP Synchro. One uses {0} and the other uses {1}. Upgrade to newest version.', 'wpsynchro'),
            'text_warning_shared_paths' => __('The web root for the {0} site is overlapping with the {1} site web root. This is not a problem if it is on a different server, but if they have overlapping paths on the same server, it will create problems if you try to synchronize all files. To prevent problems, make sure each site has its own location with no other sites inside. Database synchronization will work without problems. For more information, see the documentation on sub directory sites on wpsynchro.com', 'wpsynchro'),
            'text_save_validate_email_success_notification_failed' => __("Email list from 'notify success' in General Settings is not valid. Emails must be valid and separated by semicolon.", 'wpsynchro'),
            'text_save_validate_email_errors_notification_failed' => __("Email list from 'notify errors' in General Settings is not valid. Emails must be valid and separated by semicolon.", 'wpsynchro'),
            'limited_in_pro_title' => __('Get PRO version now to start doing file synchronization and more! Free 14 day trial - Creditcard required', 'wpsynchro'),
            'limited_in_pro_anchor_content' => __('PRO version only', 'wpsynchro'),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_addedit', $adminjsdata);

        // Location entry data and translation
        $location_entry_data = [
            'text_entry_locked' => __('This should not be synced and will be excluded from migrations', 'wpsynchro'),
            'text_entry_blocked_text' => __('Choose the entire dir or use the other add buttons', 'wpsynchro'),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_addedit_location_entry', $location_entry_data);

        // Location picker data and translation
        $location_picker_data = [
            'text_header' => __('Add files or directories to migrate', 'wpsynchro'),
            'text_keep' => __('Keep', 'wpsynchro'),
            'text_clean' => __('Clean', 'wpsynchro'),
            'text_keep_description' => __('Keep files on target not present on source. Faster, but will potentially leave unused files on target', 'wpsynchro'),
            'text_clean_description' => __('Delete files on target not present on source. Slower, but more clean, because unused files will be removed', 'wpsynchro'),
            'text_exclusions' => __('Exclusions', 'wpsynchro'),
            'text_exclusions_description' => __('Exclusions to be applied to this location. Will be matched as substring on the path, so be careful. Separate with comma. Like: ignoredir,otherignoredir', 'wpsynchro'),
            'text_cancel' => __('Cancel', 'wpsynchro'),
            'text_save' => __('Save', 'wpsynchro'),
            'text_fetchfiledata_could_not_fetch_data' => __('Could not fetch filedata - Normally due to a timed out security token. Refresh page and continue.', 'wpsynchro'),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_addedit_location_picker', $location_picker_data);

?>
        <div id="wpsynchro-addedit" class="wrap wpsynchro" v-cloak>
            <h2 class="pagetitle"><img src="<?= $commonfunctions->getAssetUrl("icon.png") ?>" width="35" height="35" />WP Synchro <?= WPSYNCHRO_VERSION ?> <?php echo ($is_pro ? 'PRO' : 'FREE'); ?> - <?php ($id > 0 ? _e('Edit installation', 'wpsynchro') : _e('Add installation', 'wpsynchro')); ?></h2>

            <?php
            if (count($compat_errors) > 0) {
                foreach ($compat_errors as $error) {
                    echo '<b>' . $error . '</b><br>';
                }
                echo '</div>';
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo "<div class='notice notice-success wpsynchro-notice'><p>" . __('Installation is now saved', 'wpsynchro') . " - <a href='" . menu_page_url('wpsynchro_overview', false) . "'>" . __('Go back to overview', 'wpsynchro') . '</a></p></div>';
            } elseif (isset($_REQUEST['created'])) {
                echo "<div class='notice notice-success wpsynchro-notice'><p>" . __('Installation is now created', 'wpsynchro') . " - <a href='" . menu_page_url('wpsynchro_overview', false) . "'>" . __('Go back to overview', 'wpsynchro') . '</a></p></div>';
            }

            echo '<p>' . __('Fill out the details of the installation to be synced to chosen location.', 'wpsynchro') . '</p>'; ?>

            <form id="wpsynchro-addedit-form" ref="addeditForm" method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

                <div class="generalsetup">
                    <div class="sectionheader"><span class="dashicons dashicons-admin-home"></span> <?php _e('Installation', 'wpsynchro'); ?></div>

                    <h3><?php _e('Choose a name', 'wpsynchro'); ?></h3>
                    <div class="option">
                        <div class="optionname">
                            <label for="name"><?php _e('Name', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <input v-model.trim="inst.name" type="text" name="name" id="name" value="" required>
                        </div>
                    </div>

                    <h3><?php _e('Type of synchronization', 'wpsynchro'); ?></h3>
                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Type', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <div><label><input v-model="inst.type" type="radio" name="type" value="pull" v-on:click="valid_endpoint = false"></input> <?php _e('Pull from remote server to this installation ', 'wpsynchro'); ?></label></div>
                            <div><label><input v-model="inst.type" type="radio" name="type" value="push" v-on:click="valid_endpoint = false"></input> <?php _e('Push this installation to remote server', 'wpsynchro'); ?></label></div>
                        </div>
                    </div>

                    <div v-if="inst.type.length > 0">
                        <h3 v-if="inst.type == 'pull'"><?php _e('Where to pull from', 'wpsynchro'); ?></h3>
                        <h3 v-if="inst.type == 'push'"><?php _e('Where to push to', 'wpsynchro'); ?></h3>

                        <div class="option">
                            <div class="optionname">
                                <label for="website"><?php _e('Website (full url)', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <input v-model.trim="inst.site_url" v-on:change="valid_endpoint = false" type="text" name="website" id="website" value="" placeholder="https://example.com" autocomplete="off" required> <span v-if="valid_endpoint" class="validstate dashicons dashicons-yes" title="<?php _e('Validated', 'wpsynchro'); ?>"></span>
                            </div>
                        </div>
                        <div class="option">
                            <div class="optionname">
                                <label for="accesskey"><?php _e('Access key', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <input v-model.trim="inst.access_key" v-on:change="valid_endpoint = false" type="password" name="accesskey" id="accesskey" value="" autocomplete="off" required></input> <span v-if="valid_endpoint" class="validstate dashicons dashicons-yes" title="<?php _e('Validated', 'wpsynchro'); ?>"></span>
                                <div v-if="!show_connection_options && inst.connection_type === 'direct'"><a href="#" v-on:click.prevent="show_connection_options = true"><?php _e('Show connection options', 'wpsynchro') ?></a> <span title="<?= __('Such as the remote site being protected by basic authentication', 'wpsynchro') ?>" class="dashicons dashicons-editor-help"></span></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="show_connection_options || inst.connection_type !== 'direct'">
                        <h3><?php _e('Connection options', 'wpsynchro'); ?></h3>

                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Connection', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <div><label><input v-model="inst.connection_type" type="radio" name="connection_type" value="direct" v-on:click="valid_endpoint = false; inst.connection_options = {};"></input> <?php _e('Direct connection (default)', 'wpsynchro'); ?></label></div>
                                <div><label><input v-model="inst.connection_type" type="radio" name="connection_type" value="basicauth" v-on:click="valid_endpoint = false" v-bind:disabled="!is_pro"></input> <?php _e('Basic authentication (username+password)', 'wpsynchro'); ?> <pro-badge v-if="!is_pro"></pro-badge></label></div>
                            </div>
                        </div>

                        <div v-if="is_pro" class="option" v-if="inst.connection_type === 'basicauth'">
                            <div class="optionname">
                                <label><?php _e('Basic authentication', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <input v-model.trim="inst.basic_auth_username" v-on:input="valid_endpoint = false" type="text" name="basic_auth_username" id="basic_auth_username" value="" placeholder="Username" autocomplete="off" data-lpignore="true" required>
                                <input v-model.trim="inst.basic_auth_password" v-on:change="valid_endpoint = false" type="password" name="basic_auth_password" id="basic_auth_password" value="" placeholder="Password" autocomplete="off" data-lpignore="true" required>
                            </div>
                        </div>

                        <div class="option">
                            <div class="optionname">
                                <label><?php _e('Verify SSL certificate', 'wpsynchro'); ?></label>
                            </div>
                            <div class="optionvalue">
                                <label><input v-model="inst.verify_ssl" v-on:change="valid_endpoint = false" type="checkbox" name="verify_ssl" id="verify_ssl"></input> <?php _e('Verify SSL certificates - Uncheck this if you want to allow self-signed certificates', 'wpsynchro'); ?></label><br>
                            </div>
                        </div>
                    </div>

                    <button id="verifyconnectionbtn" v-if="!valid_endpoint" v-bind:disabled="valid_endpoint_spinner" v-on:click.prevent="doVerification"><?php _e('Verify connection to remote site', 'wpsynchro'); ?></button>
                    <div v-show="valid_endpoint_spinner" class="spinner"></div>

                </div>

                <div class="endpoint-errors" v-if="compatibility_errors.length > 0 || valid_endpoint_errors.length > 0">
                    <div class="sectionheader sectionheadererror"><span class="dashicons dashicons-warning"></span> <?php _e('Errors was found', 'wpsynchro'); ?></div>

                    <ul>
                        <li v-for="(errormessage, index) in valid_endpoint_errors">{{errormessage}}</li>
                        <li v-for="errortext in compatibility_errors">{{errortext}}</li>
                    </ul>
                </div>

                <div class="endpoint-warnings" v-if="compatibility_warnings.length > 0 && valid_endpoint">
                    <div class="sectionheader sectionheaderwarning"><span class="dashicons dashicons-warning"></span> <?php _e('Warnings was found', 'wpsynchro'); ?></div>

                    <ul>
                        <li v-for="errortext in compatibility_warnings">{{errortext}}</li>
                    </ul>
                </div>

                <div class="multisitesetting" v-if="valid_endpoint && (this.multisite.source_is_multisite || this.multisite.target_is_multisite)">
                    <div class="sectionheader"><span class="dashicons dashicons-admin-multisite"></span> <?php _e('Multisite synchronization', 'wpsynchro'); ?> [NOT SUPPORTED]</div>

                    <p><?= __("Multisite synchronization is not supported, so if you want to try to use it anyway, make sure to test it in a safe manner.", "wpsynchro") ?></p>
                </div>

                <div class="generalsettings" v-if="valid_endpoint">
                    <div class="sectionheader"><span class="dashicons dashicons-admin-tools"></span> <?php _e('General settings', 'wpsynchro'); ?></div>

                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Clear cache on success', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <label><input v-model="inst.clear_cache_on_success" type="checkbox" name="clear_cache_on_success" id="clear_cache_on_success"></input> <?php _e('Clear the cache on the target on successful synchronization', 'wpsynchro'); ?></label>
                            <span title="<?php _e('Attempt to clear cache on target on successful synchronization - support most popular caching plugins where programmatic clearing is supported.', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span>
                        </div>
                    </div>

                    <div class="option <?= $is_pro ? "" : 'limited_in_free' ?>">
                        <div class="optionname">
                            <label for="success_notification_email_list"><?php _e('Notify emails on success', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <input type="text" v-model.trim="inst.success_notification_email_list" name="success_notification_email_list" id="success_notification_email_list" placeholder="<?= __('test@example.com;test2@example.com', 'wpsynchro') ?>" <?php echo ($is_pro ? '' : 'disabled'); ?>>
                            <span title="<?php _e('Send emails to email list when synchronization is successful.', 'wpsynchro'); ?> <?php _e('Emails are separated by semicolon. If empty, no emails will be sent.', 'wpsynchro'); ?> <?php _e('Uses WordPress standard function wp_mail() to send emails.', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span>
                            <pro-badge v-if="!is_pro"></pro-badge>
                        </div>
                    </div>

                    <div class="option <?= $is_pro ? "" : 'limited_in_free' ?>">
                        <div class="optionname">
                            <label for="error_notification_email_list"><?php _e('Notify emails on error', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <input type="text" v-model.trim="inst.error_notification_email_list" name="error_notification_email_list" id="error_notification_email_list" placeholder="<?= __('test@example.com;test2@example.com', 'wpsynchro') ?>" <?php echo ($is_pro ? '' : 'disabled'); ?>>
                            <span title="<?php _e('Send emails to email list when synchronization fails.', 'wpsynchro'); ?> <?php _e('Emails are separated by semicolon. If empty, no emails will be sent.', 'wpsynchro'); ?> <?php _e('Uses WordPress standard function wp_mail() to send emails.', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span>
                            <pro-badge v-if="!is_pro"></pro-badge>
                        </div>
                    </div>

                </div>

                <div class="datatosync" v-if="valid_endpoint">
                    <div class="sectionheader"><span class="dashicons dashicons-screenoptions"></span> <?php _e('Data to synchronize', 'wpsynchro'); ?></div>

                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Preconfigured migrations', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <div class="optionvaluepart <?= $is_pro ? "" : 'limited_in_free' ?>">
                                <label><input v-model="inst.sync_preset" type="radio" value="all" v-on:change="updateSyncPreset" name="sync_preset" id="sync_preset_everything" <?php echo ($is_pro ? '' : 'disabled'); ?>></input> <?php _e('Synchronize entire site', 'wpsynchro'); ?></label>
                                <span title="<?php _e('Backup database, synchronize database, synchronize all files from web root level (except WordPress core files)', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span>
                                <pro-badge v-if="!is_pro"></pro-badge>
                            </div>
                            <div class="optionvaluepart <?= $is_pro ? "" : 'limited_in_free' ?>">
                                <label><input v-model="inst.sync_preset" type="radio" value="file_all" v-on:change="updateSyncPreset" name="sync_preset" id="sync_preset_file_all" <?php echo ($is_pro ? '' : 'disabled'); ?>></input> <?php _e('Synchronize all files', 'wpsynchro'); ?></label>
                                <span title="<?php _e('Synchronize all files from web root level (except WordPress core files)', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span><br>
                                <pro-badge v-if="!is_pro"></pro-badge>
                            </div>
                            <div class="optionvaluepart">
                                <label><input v-model="inst.sync_preset" type="radio" value="db_all" v-on:change="updateSyncPreset" name="sync_preset" id="sync_preset_db_all"></input> <?php _e('Synchronize entire database', 'wpsynchro'); ?></label>
                                <span title="<?php echo ($is_pro ? __('Backup database and synchronize all database tables', 'wpsynchro') : __('Backup database (Only PRO version) and synchronize all database tables', 'wpsynchro')); ?>" class="dashicons dashicons-editor-help"></span>
                            </div>

                            <div class="optionvaluepart">
                                <label><input v-model="inst.sync_preset" type="radio" value="none" v-on:change="updateSyncPreset" name="sync_preset" id="sync_preset_none"></input> <?php _e('Custom synchronization', 'wpsynchro'); ?></label>
                                <span title="<?php _e('Configure exactly what you want to synchronize', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span>
                            </div>

                        </div>
                    </div>

                    <div class="option" v-if="inst.sync_preset == 'none'">
                        <div class="optionname">
                            <label><?php _e('Choose data to synchronize', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <div class="optionvaluepart <?= $is_pro ? "" : 'limited_in_free' ?>">
                                <label><input v-model="inst.sync_files" type="checkbox" name="sync_files" id="sync_files" <?php echo ($is_pro ? '' : 'disabled'); ?>></input> <?php _e('Synchronize files', 'wpsynchro'); ?> </label>
                                <pro-badge v-if="!is_pro"></pro-badge>
                            </div>
                            <div class="optionvaluepart">
                                <label><input v-model="inst.sync_database" type="checkbox" name="sync_database" id="sync_database"></input> <?php _e('Synchronize database', 'wpsynchro'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filessyncsetup" v-show="valid_endpoint && inst.sync_files && inst.sync_preset == 'none'">
                    <div class="sectionheader"><span class="dashicons dashicons-admin-page"></span> <?php _e('Files synchronization', 'wpsynchro'); ?></div>

                    <h3><?php _e('Files and directories to migrate', 'wpsynchro'); ?></h3>
                    <p><?php _e('Choose the files or directories you want to migrate and how it should be handled.', 'wpsynchro'); ?></p>

                    <?php
                    $abovewebroot_readwrite_error = __('Disabled because read or write access to this location is disabled on the source or target server - Normally by PHPs open_basedir setting', 'wpsynchro');
                    $std_readwrite_error = __('Disabled because read or write access to this location is disabled on the source or target server - Normally by incorrect file permissions', 'wpsynchro'); ?>

                    <div class="addlocations">
                        <button v-on:click.prevent="showLocationPicker('outsidewebroot',source_files_dirs.abovewebroot)" v-bind:disabled="isReadWriteRetrictedSourceTarget('abovewebroot')" v-bind:title="(isReadWriteRetrictedSourceTarget('abovewebroot') ? '<?php echo $abovewebroot_readwrite_error; ?>' : '')"><?php _e('Add from outside web root', 'wpsynchro'); ?></button>
                        <button v-on:click.prevent="showLocationPicker('webroot',source_files_dirs.webroot)" v-bind:disabled="isReadWriteRetrictedSourceTarget('webroot')" v-bind:title="(isReadWriteRetrictedSourceTarget('webroot') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Add from web root', 'wpsynchro'); ?></button>
                        <button v-on:click.prevent="showLocationPicker('wpcontent',source_files_dirs.wpcontent)" v-bind:disabled="isReadWriteRetrictedSourceTarget('wpcontent')" v-bind:title="(isReadWriteRetrictedSourceTarget('wpcontent') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Add from wp-content', 'wpsynchro'); ?></button>
                    </div>

                    <fieldset>
                        <legend>Quick add</legend>
                        <button type="button" v-on:click="quickAddFileLocation('webroot')" v-bind:disabled="isReadWriteRetrictedSourceTarget('webroot')" v-bind:title="(isReadWriteRetrictedSourceTarget('webroot') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Web root', 'wpsynchro'); ?></button>
                        <button type="button" v-on:click="quickAddFileLocation('themes')" v-bind:disabled="isReadWriteRetrictedSourceTarget('themes')" v-bind:title="(isReadWriteRetrictedSourceTarget('themes') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Themes', 'wpsynchro'); ?></button>
                        <button type="button" v-on:click="quickAddFileLocation('plugins')" v-bind:disabled="isReadWriteRetrictedSourceTarget('plugins')" v-bind:title="(isReadWriteRetrictedSourceTarget('plugins') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Plugins', 'wpsynchro'); ?></button>
                        <button type="button" v-on:click="quickAddFileLocation('uploads')" v-bind:disabled="isReadWriteRetrictedSourceTarget('uploads')" v-bind:title="(isReadWriteRetrictedSourceTarget('uploads') ? '<?php echo $std_readwrite_error; ?>' : '')"><?php _e('Uploads', 'wpsynchro'); ?></button>
                    </fieldset>

                    <h3><?php _e('Locations', 'wpsynchro'); ?></h3>
                    <p v-if="inst.file_locations.length == 0"><?php _e('No files or directories selected yet. Add them with the buttons above.', 'wpsynchro'); ?></p>

                    <div class="locationstable" v-if="inst.file_locations.length > 0">

                        <div v-if="overlapping_file_sections.length > 0" class="syncerrors">
                            <div class="iconpart">&#9940;</div>
                            <div>
                                <p><b><?php _e('Please correct these locations:', 'wpsynchro') ?></b></p>
                                <ul>
                                    <li v-for="(paths, index) in overlapping_file_sections"><?php _e('<u>{{paths[0]}}</u> overlaps with <u>{{paths[1]}}</u>', 'wpsynchro'); ?></li>
                                </ul>
                            </div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'wpsynchro'); ?></th>
                                    <th><?php _e('Full path', 'wpsynchro'); ?></th>
                                    <th><?php _e('Strategy', 'wpsynchro'); ?></th>
                                    <th><?php _e('Exclusions', 'wpsynchro'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $this->outputFileLocationRow("location.base == 'outsidewebroot'"); ?>
                                <?php $this->outputFileLocationRow("location.base == 'webroot'"); ?>
                                <?php $this->outputFileLocationRow("location.base == 'wpcontent'"); ?>
                            </tbody>
                        </table>
                    </div>

                    <h3><?php _e('General exclusions', 'wpsynchro'); ?></h3>
                    <p><?php _e('Exclude files or directories, separated by comma. Ex: .htaccess,favicon.ico,my-secret-dir', 'wpsynchro'); ?><br><?php _e('WP folders wp-admin, wp-includes and WP files in web root, as well as WP Synchro plugin and data are excluded.', 'wpsynchro'); ?><br><?php _e('These are applied to all file locations chosen in file/dir location list.', 'wpsynchro'); ?></p>
                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Exclusions', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <label><input v-model="inst.files_exclude_files_match" type="text" name="files_exclude_files_match" id="files_exclude_files_match"></input></label>
                        </div>
                    </div>

                </div>

                <div class="dbsyncsetup" v-show="valid_endpoint && inst.sync_database && inst.sync_preset == 'none'">
                    <div class="sectionheader"><span class="dashicons dashicons-update"></span> <?php _e('Database synchronization', 'wpsynchro'); ?></div>
                    <h3><?php _e('Database migration settings', 'wpsynchro'); ?></h3>
                    <div class="option <?= $is_pro ? "" : 'limited_in_free' ?>">
                        <div class="optionname">
                            <label><?php _e('Backup database tables', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <label><input v-model="inst.db_make_backup" type="checkbox" name="db_make_backup" id="db_make_backup" <?php echo ($is_pro ? '' : 'disabled'); ?>></input> <?php _e('Backup chosen database tables to file', 'wpsynchro'); ?> <span title="<?php _e('Backup database tables before overwriting them. Will be written to a .sql file that can be imported again by phpmyadmin or equal tools.', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span> (<?php _e('Recommended', 'wpsynchro'); ?>)</label>
                            <pro-badge v-if="!is_pro"></pro-badge>
                        </div>
                    </div>

                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Table prefix migration', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <label><input v-model="inst.db_table_prefix_change" type="checkbox" name="db_table_prefix_change" id="db_table_prefix_change"></input> <?php _e('Migrate table prefix and data if needed', 'wpsynchro'); ?> <span title="<?php _e('Will rename database tables, so they match the correct prefix on target - Will also rename keys in rows in options and usermeta tables. This may cause problems, if the renames accidentally renames something it shouldnt, that is custom or used by another plugin', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span> (<?php _e('Recommended', 'wpsynchro'); ?>)</label><br>
                        </div>
                    </div>

                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Active plugins', 'wpsynchro'); ?></label>
                        </div>
                        <div class="optionvalue">
                            <label><input v-model="inst.db_preserve_activeplugins" type="checkbox" name="db_preserve_activeplugins" id="db_preserve_activeplugins" checked="checked"></input> <?php _e('Preserve active plugins settings', 'wpsynchro'); ?> <span title="<?php _e('Preserve which plugins are activated and which ones are not. When enabled, you will not risk having other plugins activated, that you dont already have activated', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span> (<?php _e('Recommended', 'wpsynchro'); ?>)</label>
                        </div>
                    </div>

                    <h3><?php _e('Search/replace', 'wpsynchro'); ?></h3>
                    <p><?php _e('Add your project specific search/replaces.', 'wpsynchro'); ?><br><?php _e('Search/replace is done in a case sensitive manner and in the order listed below.', 'wpsynchro'); ?></p>


                    <div class="searchreplaces">
                        <div class="searchreplaceheadlines">
                            <div><?php _e('Search', 'wpsynchro'); ?></div>
                            <div><?php _e('Replace', 'wpsynchro'); ?></div>
                        </div>

                        <draggable v-model="inst.searchreplaces" handle=".handle">
                            <div class="searchreplace" v-for="(replace, key) in inst.searchreplaces">
                                <div class="handle dashicons dashicons-move"></div>
                                <div><input v-model="replace.from" type="text" name="searchreplaces_from[]"></input></div>
                                <div><input v-model="replace.to" type="text" name="searchreplaces_to[]"></input></div>
                                <div v-on:click="$delete(inst.searchreplaces, key)" class="deletereplace dashicons dashicons-trash"></div>
                            </div>
                        </draggable>
                    </div>

                    <div>
                        <button class="addsearchreplace" v-on:click="addSearchReplace()" type="button"><?php _e('Add replace', 'wpsynchro'); ?></button>
                        <button class="resetsearchreplace" v-on:click="createDefaultSearchReplaces()" type="button"><?php _e('Reset to recommended', 'wpsynchro'); ?></button>
                    </div>

                    <h3><?php _e('Tables to synchronize', 'wpsynchro'); ?></h3>
                    <div class="option">
                        <div class="optionname">
                            <label><?php _e('Database tables', 'wpsynchro'); ?></label>
                            <p v-if="!inst.include_all_database_tables"><?php _e('<u>Win</u>: CTRL-A to mark all - Select/deselect tables by holding CTRL while clicking table', 'wpsynchro'); ?></p>
                            <p v-if="!inst.include_all_database_tables"><?php _e('<u>Mac</u>: &#8984;-A to mark all - Select/deselect tables by holding &#8984; while clicking table', 'wpsynchro'); ?></p>
                        </div>
                        <div class="optionvalue">
                            <p><label><input v-model="inst.include_all_database_tables" type="checkbox" name="include_all_database_tables" id="include_all_database_tables" checked="checked"></input> <?php _e('Synchronize all database tables', 'wpsynchro'); ?></label></p>
                            <div v-if="! inst.include_all_database_tables" id="exclude_db_expanded_part">
                                <div>
                                    <select v-model="inst.only_include_database_table_names" id="exclude_db_tables_select" name="only_include_database_table_names[]" multiple>
                                        <option v-for="option in database_info.db_client_tables" v-bind:value="option">
                                            {{ option }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="validate-errors" v-if="validate_errors.length > 0 && valid_endpoint">
                    <div class="sectionheader sectionheadererror"><span class="dashicons dashicons-warning"></span> <?php _e('Could not save due to validation issues', 'wpsynchro'); ?></div>

                    <ul>
                        <li v-for="errortext in validate_errors">{{errortext}}</li>
                    </ul>
                </div>

                <div class="savesetup" v-if="valid_endpoint">
                    <div class="sectionheader"><span class="dashicons dashicons-edit"></span> <?php _e('Save installation', 'wpsynchro'); ?></div>
                    <p>
                        <input type="submit" v-on:click.prevent="actionsBeforeSubmit" v-if="valid_endpoint" value="<?php _e('Save', 'wpsynchro'); ?>"></input>
                    </p>
                </div>

            </form>


            <b-modal ref="locationpickermodal" id="locationpickermodal" centered hide-footer hide-header lazy>
                <locationpicker v-bind:inst="inst" v-bind:is_local="files_locationpicker.islocal" v-bind:localresturl="files_locationpicker.localresturl" v-bind:fetchresturl="files_locationpicker.fetchresturl" v-bind:relativepath="files_locationpicker.relativepath" v-bind:relativebasename="files_locationpicker.relativebasename" v-bind:blockedpaths="files_locationpicker.blockedpaths" v-bind:location_template_obj="location_template_obj" files_locationpicker @add-location="addFileLocation"></locationpicker>
            </b-modal>



        </div>
    <?php
    }

    private function outputFileLocationRow($vif)
    {
    ?>

        <tr v-for="(location, key) in inst.file_locations" v-if="<?php echo $vif; ?>">
            <input type="hidden" name="file_locations_base[]" v-bind:value="location.base"></input>
            <input type="hidden" name="file_locations_path[]" v-bind:value="location.path"></input>
            <input type="hidden" name="file_locations_strategy[]" v-bind:value="location.strategy"></input>
            <input type="hidden" name="file_locations_isfile[]" v-bind:value="location.is_file"></input>
            <input type="hidden" name="file_locations_exclusions[]" v-bind:value="location.exclusions"></input>


            <td class="type">{{ (location.is_file ? "<?php _e('File', 'wpsynchro'); ?>" : "<?php _e('Dir', 'wpsynchro'); ?>") }}</td>
            <td class="path"><code>{{ (showFullPath(location.base, location.path)) }}</code></td>

            <td class="migratestrategy">
                <div v-if="location.strategy == 'keep' && !location.is_file"><?php _e('Keep', 'wpsynchro'); ?> <span title="<?php _e('Files on target not existing on source will be kept', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span></div>
                <div v-if="location.strategy == 'clean' && !location.is_file"><?php _e('Clean', 'wpsynchro'); ?> <span title="<?php _e('Files on target not present on source will be deleted', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span></div>
                <div v-if="location.is_file"><?php _e('Overwrite', 'wpsynchro'); ?> <span title="<?php _e('File will be overwritten', 'wpsynchro'); ?>" class="dashicons dashicons-editor-help"></span></div>
            </td>
            <td class="exclu">{{ (location.exclusions ? location.exclusions : "<?php _e('N/A', 'wpsynchro'); ?>") }}</td>
            <td><span v-on:click="$delete(inst.file_locations, key)" title="<?php _e('Delete this location', 'wpsynchro'); ?>" class="deletelocation dashicons dashicons-trash"></span></td>
        </tr>

<?php
    }

    private function handlePOST()
    {
        global $wpsynchro_container;
        $inst_factory = $wpsynchro_container->get('class.InstallationFactory');
        $installation = $wpsynchro_container->get('class.Installation');
        $newly_created = false;

        if (strlen($_POST['id']) > 0) {
            // Existing installation
            $installation->id = $_POST['id'];
        } else {
            // New installation
            $installation->id = uniqid();
            $newly_created = true;
        }
        if (isset($_POST['name'])) {
            $installation->name = sanitize_text_field(trim($_POST['name']));
        } else {
            $installation->name = '';
        }
        if (isset($_POST['type'])) {
            $installation->type = sanitize_text_field($_POST['type']);
        } else {
            $installation->type = '';
        }
        if (isset($_POST['website'])) {
            $installation->site_url = sanitize_text_field(trim($_POST['website'], ',/\\ '));
        } else {
            $installation->site_url = '';
        }
        if (isset($_POST['accesskey'])) {
            $installation->access_key = sanitize_text_field(trim($_POST['accesskey']));
        } else {
            $installation->access_key = '';
        }
        // Connection type
        if (isset($_POST['connection_type'])) {
            $installation->connection_type = sanitize_text_field(trim($_POST['connection_type']));
        } else {
            $installation->connection_type = 'direct';
        }
        if (isset($_POST['basic_auth_username'])) {
            $installation->basic_auth_username = sanitize_text_field(trim($_POST['basic_auth_username']));
        } else {
            $installation->basic_auth_username = '';
        }
        if (isset($_POST['basic_auth_password'])) {
            $installation->basic_auth_password = sanitize_text_field(trim($_POST['basic_auth_password']));
        } else {
            $installation->basic_auth_password = '';
        }

        /**
         *  General settings
         */
        $installation->verify_ssl = (isset($_POST['verify_ssl']) ? true : false);

        if (isset($_POST['success_notification_email_list'])) {
            $installation->success_notification_email_list = sanitize_text_field($_POST['success_notification_email_list']);
        } else {
            $installation->success_notification_email_list = '';
        }
        if (isset($_POST['error_notification_email_list'])) {
            $installation->error_notification_email_list = sanitize_text_field($_POST['error_notification_email_list']);
        } else {
            $installation->error_notification_email_list = '';
        }

        /**
         *  Installation sync
         */
        $installation->sync_preset = (isset($_POST['sync_preset']) ? $_POST['sync_preset'] : 'none');
        $installation->sync_database = (isset($_POST['sync_database']) ? true : false);
        $installation->sync_files = (isset($_POST['sync_files']) ? true : false);

        /**
         * Database save
         */
        $installation->db_make_backup = (isset($_POST['db_make_backup']) ? true : false);
        $installation->db_table_prefix_change = (isset($_POST['db_table_prefix_change']) ? true : false);
        $installation->include_all_database_tables = (isset($_POST['include_all_database_tables']) ? true : false);
        $installation->only_include_database_table_names = (isset($_POST['only_include_database_table_names']) ? $_POST['only_include_database_table_names'] : []);
        $installation->db_preserve_activeplugins = (isset($_POST['db_preserve_activeplugins']) ? true : false);

        if (isset($_POST['searchreplaces_from'])) {
            $searchreplaces_from = $_POST['searchreplaces_from'];
        } else {
            $searchreplaces_from = [];
        }
        if (isset($_POST['searchreplaces_to'])) {
            $searchreplaces_to = $_POST['searchreplaces_to'];
        } else {
            $searchreplaces_to = [];
        }

        $searchreplaces = [];
        for ($i = 0; $i < count($searchreplaces_from); $i++) {
            if (strlen($searchreplaces_from[$i]) > 0 && strlen($searchreplaces_to[$i]) > 0) {
                $tmp_obj = new \stdClass();
                $tmp_obj->to = stripslashes($searchreplaces_to[$i]);
                $tmp_obj->from = stripslashes($searchreplaces_from[$i]);
                $searchreplaces[] = $tmp_obj;
            }
        }
        $installation->searchreplaces = $searchreplaces;

        /**
         * Files save
         */
        $file_locations = [];

        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            if (isset($_POST['file_locations_base'])) {
                $file_locations_base = $_POST['file_locations_base'];
            }
            if (isset($_POST['file_locations_strategy'])) {
                $file_locations_strategy = $_POST['file_locations_strategy'];
            }
            if (isset($_POST['file_locations_isfile'])) {
                $file_locations_isfile = $_POST['file_locations_isfile'];
            }
            if (isset($_POST['file_locations_exclusions'])) {
                $file_locations_exclusions = $_POST['file_locations_exclusions'];
            }
            if (isset($_POST['file_locations_path'])) {
                $file_locations_path = $_POST['file_locations_path'];
            }

            if (isset($file_locations_path)) {
                for ($i = 0; $i < count($file_locations_path); $i++) {
                    $location = $wpsynchro_container->get('class.Location');
                    $location->path = $file_locations_path[$i];
                    if (isset($file_locations_base[$i])) {
                        $location->base = $file_locations_base[$i];
                    }
                    if (isset($file_locations_strategy[$i])) {
                        $location->strategy = $file_locations_strategy[$i];
                    }
                    if (isset($file_locations_isfile[$i])) {
                        $location->is_file = ($file_locations_isfile[$i] == 'true' ? true : false);
                    }
                    if (isset($file_locations_exclusions[$i])) {
                        $location->exclusions = $file_locations_exclusions[$i];
                    }
                    $file_locations[] = $location;
                }
            }
        }

        $installation->file_locations = $file_locations;

        if (isset($_POST['files_exclude_files_match'])) {
            $installation->files_exclude_files_match = sanitize_text_field($_POST['files_exclude_files_match']);
        } else {
            $installation->files_exclude_files_match = '';
        }

        $inst_factory->addInstallation($installation);

        if ($newly_created) {
            $redirurl = add_query_arg('syncid', $installation->id, menu_page_url('wpsynchro_addedit', false));
            $redirurl = add_query_arg('created', '1', $redirurl);
            echo "<script>window.location.replace('" . $redirurl . "');</script>";
        }
    }
}
