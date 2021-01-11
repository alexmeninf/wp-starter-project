<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Background_Processes;

class Remove_Local_Files_Process extends Background_Tool_Process {

	/**
	 * @var string
	 */
	protected $action = 'remove_local_files';

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

		foreach ( $attachments as $key => $attachment_id ) {
			if ( ! $this->as3cf->is_attachment_served_by_provider( $attachment_id, true ) || ! $this->as3cf->attachment_exist_locally( $attachment_id ) ) {
				unset( $attachments[ $key ] );
			}
		}

		$keys = $this->as3cf->get_provider_keys( $attachments );

		foreach ( $attachments as $attachment_id ) {
			$this->as3cf->delete_local_attachment( $attachment_id, $keys );
		}

		// Whether removed from local or not, we processed every item.
		return $processed;
	}

	/**
	 * Get complete notice message.
	 *
	 * @return string
	 */
	protected function get_complete_message() {
		return __( '<strong>WP Offload Media</strong> &mdash; Finished removing media files from local server.', 'amazon-s3-and-cloudfront' );
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
