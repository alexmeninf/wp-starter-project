<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Background_Processes;

use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use Exception;

class Copy_Buckets_Process extends Background_Tool_Process {

	/**
	 * @var string
	 */
	protected $action = 'copy_buckets';

	/**
	 * Process attachments chunk.
	 *
	 * @param array $attachments
	 * @param int   $blog_id
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function process_attachments_chunk( $attachments, $blog_id ) {
		$bucket = $this->as3cf->get_setting( 'bucket' );
		$region = $this->as3cf->get_setting( 'region' );

		$attachments_to_copy = array();

		foreach ( $attachments as $attachment_id ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

			if ( $bucket === $as3cf_item->bucket() ) {
				continue;
			}

			$attachments_to_copy[] = $attachment_id;
		}

		$this->copy_attachments( $attachments_to_copy, $blog_id, $bucket, $region );

		// Whether copied or not, we processed every item.
		return $attachments;
	}

	/**
	 * Copy attachments to new bucket.
	 *
	 * @param array  $attachments
	 * @param int    $blog_id
	 * @param string $bucket
	 * @param string $region
	 *
	 * @throws Exception
	 */
	protected function copy_attachments( $attachments, $blog_id, $bucket, $region ) {
		if ( empty( $attachments ) ) {
			return;
		}

		$keys = $this->as3cf->get_provider_keys( $attachments );

		if ( empty( $keys ) ) {
			return;
		}

		$items   = array();
		$skipped = array();

		foreach ( $keys as $attachment_id => $attachment_keys ) {
			// If the attachment is offloaded to another provider, skip it.
			if ( ! $this->as3cf->is_attachment_served_by_provider( $attachment_id, true ) ) {
				$skipped[] = array(
					'Key'     => $attachment_keys[0],
					'Message' => sprintf( __( 'Attachment ID %s is offloaded to a different provider than currently configured', 'amazon-s3-and-cloudfront' ), $attachment_id ),
				);
				continue;
			}

			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

			foreach ( $attachment_keys as $key ) {
				$args    = array(
					'Bucket'     => $bucket,
					'Key'        => $key,
					'CopySource' => urlencode( "{$as3cf_item->bucket()}/{$key}" ),
					'ACL'        => $this->determine_key_acl( $attachment_id, $key ),
				);
				$size    = AS3CF_Utils::get_intermediate_size_from_filename( $attachment_id, wp_basename( $key ) );
				$items[] = apply_filters( 'as3cf_object_meta', $args, $attachment_id, $size, true );
			}
		}

		$failures = array();

		if ( ! empty( $items ) ) {
			$client = $this->as3cf->get_provider_client( $region, true );
			try {
				$failures = $client->copy_objects( $items );
			} catch ( Exception $e ) {
				AS3CF_Error::log( $e->getMessage() );

				return;
			}
		}

		$failures = $failures + $skipped;

		if ( ! empty( $failures ) ) {
			$keys = $this->handle_failed_keys( $keys, $failures, $blog_id );
		}

		$this->update_attachment_provider_info( $keys, $bucket, $region );
	}

	/**
	 * Determine ACL for key.
	 *
	 * @param int    $attachment_id
	 * @param string $key
	 *
	 * @return string
	 */
	protected function determine_key_acl( $attachment_id, $key ) {
		$filename = wp_basename( $key );
		$size     = AS3CF_Utils::get_intermediate_size_from_filename( $attachment_id, $filename );

		return $this->as3cf->get_acl_for_intermediate_size( $attachment_id, $size );
	}

	/**
	 * Handle failed keys.
	 *
	 * @param array $keys
	 * @param array $failures
	 * @param int   $blog_id
	 *
	 * @return array
	 */
	protected function handle_failed_keys( $keys, $failures, $blog_id ) {
		foreach ( $failures as $failure ) {
			foreach ( $keys as $attachment_id => $attachment_keys ) {
				if ( false !== array_search( $failure['Key'], $attachment_keys ) ) {
					$error_msg = sprintf( __( 'Error copying %s between buckets: %s', 'amazon-s3-and-cloudfront' ), $failure['Key'], $failure['Message'] );

					$this->record_error( $blog_id, $attachment_id, $error_msg );

					unset( $keys[ $attachment_id ] );

					break;
				}
			}
		}

		return $keys;
	}

	/**
	 * Update attachment S3 info.
	 *
	 * @param array  $keys
	 * @param string $bucket
	 * @param string $region
	 */
	protected function update_attachment_provider_info( $keys, $bucket, $region ) {
		if ( empty( $keys ) ) {
			return;
		}

		foreach ( $keys as $attachment_id => $attachment_keys ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

			$as3cf_item = new Media_Library_Item(
				$as3cf_item->provider(),
				$region,
				$bucket,
				$as3cf_item->path(),
				$as3cf_item->is_private(),
				$as3cf_item->source_id(),
				$as3cf_item->source_path(),
				wp_basename( $as3cf_item->original_source_path() ),
				$as3cf_item->private_sizes(),
				$as3cf_item->id()
			);

			$as3cf_item->save();
		}
	}

	/**
	 * Get complete notice message.
	 *
	 * @return string
	 */
	protected function get_complete_message() {
		return __( '<strong>WP Offload Media</strong> &mdash; Finished copying media files to new bucket.', 'amazon-s3-and-cloudfront' );
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