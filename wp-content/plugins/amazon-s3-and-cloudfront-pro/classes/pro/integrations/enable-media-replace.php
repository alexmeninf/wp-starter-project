<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Integrations;

use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use Exception;

class Enable_Media_Replace extends Integration {

	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public function is_installed() {
		if ( class_exists( 'EnableMediaReplace\EnableMediaReplacePlugin' ) || function_exists( 'enable_media_replace_init' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Init integration.
	 */
	public function init() {
		// Make sure EMR allows OME to filter get_attached_file.
		add_filter( 'emr_unfiltered_get_attached_file', '__return_false' );

		// Download the files and return their path so EMR doesn't get tripped up.
		add_filter( 'as3cf_get_attached_file', array( $this, 'download_file' ), 10, 4 );

		// Although EMR uses wp_unique_filename, it discards that potentially new filename for plain replace, but does then use the following filter.
		add_filter( 'emr_unique_filename', array( $this, 'ensure_unique_filename' ), 10, 3 );

		// Remove objects before offload happens, but don't re-offload just yet.
		add_filter( 'as3cf_update_attached_file', array( $this, 'remove_existing_provider_files_during_replace' ), 10, 2 );
	}

	/**
	 * Allow the Enable Media Replace plugin to copy the provider file back to the local
	 * server when the file is missing on the server via get_attached_file().
	 *
	 * @param string             $url
	 * @param string             $file
	 * @param int                $attachment_id
	 * @param Media_Library_Item $as3cf_item
	 *
	 * @return string
	 */
	function download_file( $url, $file, $attachment_id, Media_Library_Item $as3cf_item ) {
		return $this->as3cf->plugin_compat->copy_image_to_server_on_action( 'media_replace_upload', false, $url, $file, $as3cf_item );
	}

	/**
	 * EMR deletes the original files before replace, then updates metadata etc.
	 * So we should remove associated offloaded files too, and let normal (re)offload happen afterwards.
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 *
	 * @return string
	 * @throws Exception
	 */
	function remove_existing_provider_files_during_replace( $file, $attachment_id ) {
		if ( ! $this->is_replacing_media() ) {
			return $file;
		}

		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return $file;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

		if ( ! empty( $as3cf_item ) ) {
			$this->as3cf->remove_attachment_files_from_provider( $attachment_id, $as3cf_item );
			$as3cf_item->delete();
		}

		return $file;
	}

	/**
	 * Are we doing a media replacement?
	 *
	 * @return bool
	 */
	public function is_replacing_media() {
		$action = filter_input( INPUT_GET, 'action' );

		if ( empty( $action ) ) {
			return false;
		}

		return ( 'media_replace_upload' === sanitize_key( $action ) );
	}

	/**
	 * Ensure the generated filename for an image replaced with a new image is unique.
	 *
	 * @param string $filename File name that should be unique.
	 * @param string $path     Absolute path to where the file will go.
	 * @param int    $id       Attachment ID.
	 *
	 * @return string
	 */
	public function ensure_unique_filename( $filename, $path, $id ) {
		// Get extension.
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		$ext = $ext ? ".$ext" : '';

		return $this->as3cf->filter_unique_filename( $filename, $ext, $path, $id );
	}
}
