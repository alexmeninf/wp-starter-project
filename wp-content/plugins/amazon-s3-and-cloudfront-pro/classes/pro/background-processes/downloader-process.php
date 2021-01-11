<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Background_Processes;

class Downloader_Process extends Background_Tool_Process {

	/**
	 * @var string
	 */
	protected $action = 'downloader';

	/**
	 * Process attachments chunk.
	 *
	 * @param array $attachments
	 * @param int   $blog_id
	 *
	 * @return array
	 *
	 * @throws \Exception
	 */
	protected function process_attachments_chunk( $attachments, $blog_id ) {
		$processed = $attachments;

		foreach ( $attachments as $attachment_id ) {
			$this->handle_attachment( $attachment_id, $blog_id );
		}

		// Whether downloaded to local or not, we processed every item.
		return $processed;
	}

	/**
	 * Download the attachment from bucket.
	 *
	 * @param int $attachment_id
	 * @param int $blog_id
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function handle_attachment( $attachment_id, $blog_id ) {
		// Copy back bucket file to local, only when files don't exist locally.
		$result = $this->as3cf->download_attachment_from_provider( $attachment_id, true, true );

		if ( is_wp_error( $result ) ) {
			if ( $this->count_errors() <= 100 ) {
				foreach ( $result->get_error_messages() as $error_message ) {
					$error_msg = sprintf( __( 'Error downloading to server - %s', 'amazon-s3-and-cloudfront' ), $error_message );
					$this->record_error( $blog_id, $attachment_id, $error_msg );
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Get complete notice message.
	 *
	 * @return string
	 */
	protected function get_complete_message() {
		return __( '<strong>WP Offload Media</strong> &mdash; Finished downloading media files to local server.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Called when background process has been cancelled.
	 */
	protected function cancelled() {
		// Do nothing at the moment.
	}

	/**
	 * Called when background process has been paused.
	 */
	protected function paused() {
		// Do nothing at the moment.
	}

	/**
	 * Called when background process has been resumed.
	 */
	protected function resumed() {
		// Do nothing at the moment.
	}

	/**
	 * Called when background process has completed.
	 */
	protected function completed() {
		// Do nothing at the moment.
	}
}
