<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Background_Processes;

class Download_And_Remover_Process extends Downloader_Process {

	/**
	 * @var string
	 */
	protected $action = 'download_and_remover';

	/**
	 * Download and remove the attachment from bucket.
	 *
	 * @param int $attachment_id
	 * @param int $blog_id
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function handle_attachment( $attachment_id, $blog_id ) {
		if ( parent::handle_attachment( $attachment_id, $blog_id ) ) {
			$this->as3cf->delete_attachment( $attachment_id, true );

			return true;
		}

		return false;
	}

	/**
	 * Called when background process has been cancelled.
	 */
	protected function cancelled() {
		$this->as3cf->update_media_library_total();
	}

	/**
	 * Called when background process has been paused.
	 */
	protected function paused() {
		$this->as3cf->update_media_library_total();
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
		$this->as3cf->update_media_library_total();
	}

	/**
	 * Get complete notice message.
	 *
	 * @return string
	 */
	protected function get_complete_message() {
		return __( '<strong>WP Offload Media</strong> &mdash; Finished removing media files from bucket.', 'amazon-s3-and-cloudfront' );
	}
}
