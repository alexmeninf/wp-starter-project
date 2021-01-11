<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Tools;

use DeliciousBrains\WP_Offload_Media\Pro\Background_Processes\Background_Tool_Process;
use DeliciousBrains\WP_Offload_Media\Pro\Background_Processes\Uploader_Process;
use DeliciousBrains\WP_Offload_Media\Pro\Background_Tool;

class Uploader extends Background_Tool {

	/**
	 * @var string
	 */
	protected $tool_key = 'uploader';

	/**
	 * Initialize Uploader
	 */
	public function init() {
		parent::init();

		$this->error_setting_migration();
	}

	/**
	 * Migrate old upload errors to new setting key
	 */
	protected function error_setting_migration() {
		if ( false !== ( $errors = $this->as3cf->get_setting( 'bulk_upload_errors', false ) ) ) {
			$this->update_errors( $errors );
			$this->as3cf->remove_setting( 'bulk_upload_errors' );
			$this->as3cf->save_settings();
		}
	}

	/**
	 * Get the details for the sidebar block
	 *
	 * @return array|false
	 * @throws \Exception
	 */
	protected function get_sidebar_block_args() {
		if ( ! $this->as3cf->is_pro_plugin_setup() ) {
			// Don't show tool if pro not setup
			return false;
		}

		// Don't show upload tool if bucket isn't writable
		$can_write = $this->as3cf->check_write_permission();
		if ( ! $can_write || is_wp_error( $can_write ) ) {
			return false;
		}

		$media_counts = $this->as3cf->media_counts();

		// Don't show upload tool if media library empty.
		if ( 0 === $media_counts['total'] ) {
			return false;
		}

		$human_percentage = $this->percent_offloaded();

		// Required for JS.
		$states = array(
			0   => 'initial',
			1   => 'partial_complete',
			100 => 'complete',
		);

		// Required for JS.
		$i18n = array(
			'title_initial'           => __( 'Your Media Library needs to be offloaded', 'amazon-s3-and-cloudfront' ),
			'title_partial_complete'  => __( "%s%% of your Media Library has been offloaded", 'amazon-s3-and-cloudfront' ),
			'title_complete'          => __( '100% of your Media Library has been offloaded, congratulations!', 'amazon-s3-and-cloudfront' ),
			'upload_initial'          => __( 'Offload Now', 'amazon-s3-and-cloudfront' ),
			'upload_partial_complete' => __( 'Offload Remaining Now', 'amazon-s3-and-cloudfront' ),
		);

		switch ( $human_percentage ) {
			case 0 : // Entire library needs uploading
				$title              = $i18n['title_initial'];
				$upload_button_text = $i18n['upload_initial'];
				break;

			case 100 : // Entire media library uploaded
				$title              = $i18n['title_complete'];
				$upload_button_text = $i18n['upload_partial_complete'];

				// Remove previous errors
				$this->clear_errors();
				$this->as3cf->notices->remove_notice_by_id( $this->errors_key );
				break;

			default: // Media library upload partially complete
				$title              = sprintf( $i18n['title_partial_complete'], $human_percentage );
				$upload_button_text = $i18n['upload_partial_complete'];
		}

		$args = array(
			'title'             => $title,
			'total_progress'    => $human_percentage,
			'progress'          => $this->get_progress(),
			'total_on_provider' => (int) $media_counts['offloaded'],
			'total_items'       => (int) $media_counts['total'],
			'button'            => $upload_button_text,
			'pie_chart'         => 1,
			'i18n'              => $i18n,
			'states'            => $states,
		);

		return array_merge( parent::get_sidebar_block_args(), $args );
	}

	/**
	 * Calculate the percentage of media library items offloaded, to the nearest integer.
	 *
	 * @return int
	 */
	private function percent_offloaded() {
		static $human_percentage;

		if ( is_null( $human_percentage ) ) {
			$media_counts = $this->as3cf->media_counts();

			if ( empty( $media_counts['total'] ) ) {
				$human_percentage = 100;
			} elseif ( empty( $media_counts['offloaded'] ) ) {
				$human_percentage = 0;
			} else {
				$uploaded_percentage = (float) $media_counts['offloaded'] / $media_counts['total'];
				$human_percentage    = (int) floor( $uploaded_percentage * 100 );

				// Percentage of library needs uploading.
				if ( 0 === $human_percentage && $uploaded_percentage > 0 ) {
					$human_percentage = 1;
				}
			}
		}

		return $human_percentage;
	}

	/**
	 * Retrieve the license notice so it can be refreshed behind the tool modal
	 *
	 * @return array
	 */
	protected function get_custom_notices_to_update() {
		$notices = parent::get_custom_notices_to_update();

		$notice_id = $this->get_tool_key() . '_license_limit';
		$notice    = $this->as3cf->notices->find_notice_by_id( $notice_id );

		if ( ! empty( $notice ) ) {
			$notices[] = $notice;
		}

		return $notices;
	}

	/**
	 * Message for error notice
	 *
	 * @param null $message Optional message to override the default for the tool.
	 *
	 * @return string
	 */
	protected function get_error_notice_message( $message = null ) {
		$title   = __( 'Offload Errors', 'amazon-s3-and-cloudfront' );
		$message = empty( $message ) ? __( 'Previous attempts at offloading your media library have resulted in errors.', 'amazon-s3-and-cloudfront' ) : $message;

		return sprintf( '<strong>%s</strong> &mdash; %s', $title, $message );
	}

	/**
	 * Get status.
	 *
	 * @return array
	 */
	public function get_status() {
		$media_counts = $this->as3cf->media_counts();

		return array_merge( parent::get_status(), array(
			'total_progress'    => $this->percent_offloaded(),
			'total_on_provider' => (int) $media_counts['offloaded'],
			'total_items'       => (int) $media_counts['total'],
		) );
	}

	/**
	 * Handle start.
	 */
	public function handle_start() {
		$notice_id = $this->get_tool_key() . '_license_limit';
		$this->as3cf->notices->remove_notice_by_id( $notice_id );

		parent::handle_start();
	}

	/**
	 * Get title text.
	 *
	 * @return string
	 */
	public function get_title_text() {
		return '';
	}

	/**
	 * Get button text.
	 *
	 * @return string
	 */
	public function get_button_text() {
		return '';
	}

	/**
	 * Get queued status text.
	 *
	 * @return string
	 */
	public function get_queued_status() {
		return __( 'Offloading Media Library items to bucket.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get background process class.
	 *
	 * @return Background_Tool_Process|null
	 */
	protected function get_background_process_class() {
		return new Uploader_Process( $this->as3cf, $this );
	}
}
