<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Integrations;

use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use Exception;

class Woocommerce extends Integration {

	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public function is_installed() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Init integration.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_ajax_as3cf_woo_is_amazon_provider_attachment', array( $this, 'ajax_is_amazon_provider_attachment' ) );
		add_action( 'woocommerce_process_product_file_download_paths', array( $this, 'make_files_private_on_provider' ), 10, 3 );
		add_action( 'woocommerce_download_file_as3cf', array( $this, 'download_file' ), 10, 2 );
		add_filter( 'woocommerce_file_download_method', array( $this, 'add_download_method' ) );
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function admin_scripts() {
		$screen = get_current_screen();

		if ( in_array( $screen->id, array( 'product', 'edit-product' ) ) ) {
			if ( ! $this->as3cf->is_pro_plugin_setup( true ) ) {
				// Don't allow new shortcodes if Pro not set up
				return;
			}

			wp_enqueue_media();
			$this->as3cf->enqueue_script( 'as3cf-woo-script', 'assets/js/pro/integrations/woocommerce', array(
				'jquery',
				'wp-util',
			) );

			wp_localize_script( 'as3cf-woo-script', 'as3cf_woo', array(
				'strings' => array(
					'media_modal_title'  => __( 'Select Downloadable File', 'as3cf-woocommerce' ),
					'media_modal_button' => __( 'Insert File', 'as3cf-woocommerce' ),
					'input_placeholder'  => __( 'Retrieving...', 'as3cf-woocommerce' ),
				),
				'nonces'  => array(
					'is_amazon_provider_attachment' => wp_create_nonce( 'as3cf_woo_is_amazon_provider_attachment' ),
				),
			) );
		}
	}

	/**
	 * Ajax get s3 info.
	 */
	public function ajax_is_amazon_provider_attachment() {
		$return = false;

		/**
		 * Filter to allow changing the user capability required for adding an offloaded item to a WooCommerce product.
		 *
		 * @param string $capability Registered capability identifier
		 */
		$capability = apply_filters( 'as3cfpro_woo_use_attachment_capability', null );

		if ( $this->as3cf->verify_ajax_request( $capability, true ) && Media_Library_Item::get_by_source_id( intval( $_POST['attachment_id'] ) ) ) {
			$return = true;
		}

		wp_send_json_success( $return );
	}

	/**
	 * Make file private on Amazon S3.
	 *
	 * @param int   $post_id
	 * @param int   $deprecated
	 * @param array $files
	 *
	 * @return array
	 */
	public function make_files_private_on_provider( $post_id, $deprecated, $files ) {
		$new_attachments = array();

		foreach ( $files as $file ) {
			$attachment_id = $this->get_attachment_id_from_shortcode( $file['file'] );

			if ( false === $attachment_id ) {
				// Attachment id could not be determined, ignore
				continue;
			}

			$new_attachments[] = $attachment_id;

			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

			if ( ! $as3cf_item ) {
				// Not offloaded, ignore.
				continue;
			}

			if ( $this->as3cf->is_pro_plugin_setup( true ) ) {
				// Only set new files as private if the Pro plugin is setup
				$as3cf_item = $this->as3cf->set_attachment_acl_on_provider( $attachment_id, $as3cf_item, true );
				if ( $as3cf_item && ! is_wp_error( $as3cf_item ) ) {
					$this->as3cf->make_acl_admin_notice( $as3cf_item );
				}
			}
		}

		$this->maybe_make_removed_files_public( $post_id, $new_attachments );

		return $files;
	}

	/**
	 * Get attachment id from shortcode.
	 *
	 * @param string $shortcode
	 *
	 * @return int|bool
	 */
	protected function get_attachment_id_from_shortcode( $shortcode ) {
		global $wpdb;

		$atts = $this->get_shortcode_atts( $shortcode );

		if ( isset( $atts['id'] ) ) {
			return $atts['id'];
		}

		if ( ! isset( $atts['bucket'] ) || ! isset( $atts['object'] ) ) {
			return false;
		}

		return Media_Library_Item::get_source_id_by_bucket_and_path( $atts['bucket'], $atts['object'] );
	}

	/**
	 * Get shortcode atts.
	 *
	 * @param string $shortcode
	 *
	 * @return array
	 */
	protected function get_shortcode_atts( $shortcode ) {
		$shortcode = trim( stripcslashes( $shortcode ) );
		$shortcode = ltrim( $shortcode, '[' );
		$shortcode = rtrim( $shortcode, ']' );
		$shortcode = shortcode_parse_atts( $shortcode );

		return $shortcode;
	}

	/**
	 * Remove private ACL from S3 if no longer used by WooCommerce.
	 *
	 * @param int   $post_id
	 * @param array $new_attachments
	 *
	 * @return void
	 */
	protected function maybe_make_removed_files_public( $post_id, $new_attachments ) {
		$old_files       = get_post_meta( $post_id, '_downloadable_files', true );
		$old_attachments = array();

		if ( is_array( $old_files ) ) {
			foreach ( $old_files as $old_file ) {
				$attachment_id = $this->get_attachment_id_from_shortcode( $old_file['file'] );

				if ( false !== $attachment_id ) {
					$old_attachments[] = $attachment_id;
				}
			}
		}

		$removed_attachments = array_diff( $old_attachments, $new_attachments );

		if ( empty( $removed_attachments ) ) {
			return;
		}

		global $wpdb;

		foreach ( $removed_attachments as $attachment_id ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

			if ( ! $as3cf_item ) {
				// Not offloaded, ignore.
				continue;
			}

			$bucket = preg_quote( $as3cf_item->bucket(), '@' );
			$key    = preg_quote( $as3cf_item->path(), '@' );

			// Check the attachment isn't used by other downloads
			$sql = $wpdb->prepare( "
				SELECT meta_value
				FROM $wpdb->postmeta
				WHERE post_id != %d
				AND meta_key = %s
				AND meta_value LIKE %s
			", $post_id, '_downloadable_files', '%amazon_s3%' );

			$results = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $results as $result ) {
				// WP Offload Media
				if ( preg_match( '@\[amazon_s3\sid=[\'\"]*' . $attachment_id . '[\'\"]*\]@', $result['meta_value'] ) ) {
					continue 2;
				}

				// Official WooCommerce S3 addon
				if ( preg_match( '@\[amazon_s3\sobject=[\'\"]*' . $key . '[\'\"]*\sbucket=[\'\"]*' . $bucket . '[\'\"]*\]@', $result['meta_value'] ) ) {
					continue 2;
				}
				if ( preg_match( '@\[amazon_s3\sbucket=[\'\"]*' . $bucket . '[\'\"]*\sobject=[\'\"]*' . $key . '[\'\"]*\]@', $result['meta_value'] ) ) {
					continue 2;
				}
			}

			// Set ACL to public
			$as3cf_item = $this->as3cf->set_attachment_acl_on_provider( $attachment_id, $as3cf_item, false );

			if ( $as3cf_item && ! is_wp_error( $as3cf_item ) ) {
				$this->as3cf->make_acl_admin_notice( $as3cf_item );
			}
		}
	}

	/**
	 * Add download method to WooCommerce.
	 *
	 * @return string
	 */
	public function add_download_method() {
		return 'as3cf';
	}

	/**
	 * Use S3 secure link to download file.
	 *
	 * @param string $file_path
	 * @param int    $filename
	 *
	 * @return void
	 */
	public function download_file( $file_path, $filename ) {
		$attachment_id = $this->get_attachment_id_from_shortcode( $file_path );

		$expires = apply_filters( 'as3cf_woocommerce_download_expires', 5 );

		$file_data = array(
			'name' => $filename,
			'file' => $file_path,
		);

		if ( ! $attachment_id || ! Media_Library_Item::get_by_source_id( $attachment_id ) ) {
			/*
			This addon is meant to be a drop-in replacement for the
			WooCommerce Amazon S3 Storage extension. The latter doesn't encourage people
			to add the file to the Media Library, so even though we can't get an
			attachment ID for the shortcode, we should still serve the download
			if the shortcode contains the `bucket` and `object` attributes.
			*/
			$atts = $this->get_shortcode_atts( $file_path );

			if ( isset( $atts['bucket'] ) && isset( $atts['object'] ) ) {
				$bucket_setting = $this->as3cf->get_setting( 'bucket' );

				if ( $bucket_setting === $atts['bucket'] ) {
					$region = $this->as3cf->get_setting( 'region' );
				} else {
					$region = $this->as3cf->get_bucket_region( $atts['bucket'], true );
				}

				try {
					$expires    = time() + $expires;
					$headers    = apply_filters( 'as3cf_woocommerce_download_headers', array( 'ResponseContentDisposition' => 'attachment' ), $file_data );
					$secure_url = $this->as3cf->get_provider_client( $region, true )->get_object_url( $atts['bucket'], $atts['object'], $expires, $headers );
				} catch ( Exception $e ) {
					return;
				}

				header( 'Location: ' . $secure_url );
				exit;
			}

			// Handle shortcode inputs where the file has been removed from S3
			// Parse the url, shortcodes do not return a host
			$url = parse_url( $file_path );

			if ( ! isset( $url['host'] ) ) {
				$file_path = wp_get_attachment_url( $attachment_id );
				$filename  = wp_basename( $file_path );
			}

			// File not on S3, trigger WooCommerce saved download method
			$method = get_option( 'woocommerce_file_download_method', 'force' );
			do_action( 'woocommerce_download_file_' . $method, $file_path, $filename );

			return;
		}

		$file_data['attachment_id'] = $attachment_id;
		$headers                    = apply_filters( 'as3cf_woocommerce_download_headers', array( 'ResponseContentDisposition' => 'attachment' ), $file_data );
		$secure_url                 = $this->as3cf->get_secure_attachment_url( $attachment_id, $expires, null, $headers, true );

		header( 'Location: ' . $secure_url );
		exit;
	}
}
