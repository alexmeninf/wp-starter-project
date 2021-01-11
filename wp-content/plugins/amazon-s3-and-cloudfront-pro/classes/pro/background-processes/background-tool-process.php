<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Background_Processes;

use AS3CF_Background_Process;
use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use DeliciousBrains\WP_Offload_Media\Pro\Tool;
use DeliciousBrains\WP_Offload_Media\Providers\Provider;

abstract class Background_Tool_Process extends AS3CF_Background_Process {

	/**
	 * @var Tool
	 */
	protected $tool;

	/**
	 * Default batch limit.
	 *
	 * @var int
	 */
	protected $limit = 100;

	/**
	 * Default chunk size.
	 *
	 * @var int
	 */
	protected $chunk = 10;

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Initiate new background tool process.
	 *
	 * @param object $as3cf Instance of calling class
	 * @param Tool   $tool
	 */
	public function __construct( $as3cf, $tool ) {
		parent::__construct( $as3cf );

		$this->tool = $tool;
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		if ( ! $item['blogs_processed'] ) {
			// Calculate how many attachments each blog has
			$item = $this->calculate_blog_attachments( $item );
		}

		if ( $this->all_blog_attachments_processed( $item ) ) {
			// Batch complete, remove from queue
			return false;
		}

		return $this->process_blogs( $item );
	}

	/**
	 * Calculate the number of attachments across all blogs.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function calculate_blog_attachments( $item ) {
		foreach ( $item['blogs'] as $blog_id => $blog ) {
			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				// Batch limits reached
				return $item;
			}

			if ( ! is_null( $blog['total_attachments'] ) ) {
				// Blog already processed, move on
				continue;
			}

			$last_attachment_id = ! empty( $blog['last_attachment_id'] ) ? $blog['last_attachment_id'] : null;

			$this->as3cf->switch_to_blog( $blog_id );

			$total = $this->get_blog_attachments( $last_attachment_id, null, true );

			if ( ! empty( $total ) ) {
				$item['blogs'][ $blog_id ]['total_attachments']  = $total;
				$item['blogs'][ $blog_id ]['last_attachment_id'] = $this->get_blog_last_attachment_id() + 1;
				$item['total_attachments']                       += $total;
			} else {
				$item['blogs'][ $blog_id ]['processed']         = true;
				$item['blogs'][ $blog_id ]['total_attachments'] = 0;
			}

			$this->as3cf->restore_current_blog();
		}

		$item['blogs_processed'] = true;

		return $item;
	}

	/**
	 * Loop over each blog and process attachments.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function process_blogs( $item ) {
		$this->errors = $this->tool->get_errors();

		foreach ( $item['blogs'] as $blog_id => $blog ) {
			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				// Batch limits reached
				break;
			}

			if ( $blog['processed'] ) {
				// Blog processed, move onto the next
				continue;
			}

			$last_attachment_id = ! empty( $blog['last_attachment_id'] ) ? $blog['last_attachment_id'] : null;

			$this->as3cf->switch_to_blog( $blog_id );

			$limit       = apply_filters( "as3cf_tool_{$this->action}_batch_size", $this->limit );
			$attachments = $this->get_blog_attachments( $last_attachment_id, $limit );
			$item        = $this->process_blog_attachments( $item, $blog_id, $attachments );

			$this->as3cf->restore_current_blog();
		}

		if ( count( $this->errors ) ) {
			$this->tool->update_errors( $this->errors );
			$this->tool->update_error_notice( $this->errors );
			$this->tool->undismiss_error_notice();
		}

		return $item;
	}

	/**
	 * Process blog attachments.
	 *
	 * @param array $item
	 * @param int   $blog_id
	 * @param array $attachments
	 *
	 * @return array
	 */
	protected function process_blog_attachments( $item, $blog_id, $attachments ) {
		$chunks = array_chunk( $attachments, $this->chunk );

		foreach ( $chunks as $chunk ) {
			$processed = $this->process_attachments_chunk( $chunk, $blog_id );

			if ( ! empty( $processed ) ) {
				$item['processed_attachments']                   += count( $processed );
				$item['blogs'][ $blog_id ]['last_attachment_id'] = end( $processed );
			}

			if ( $this->time_exceeded() || $this->memory_exceeded() || count( $chunk ) > count( $processed ) ) {
				break;
			}
		}

		if ( empty( $attachments ) ) {
			$item['blogs'][ $blog_id ]['processed'] = true;
		}

		return $item;
	}

	/**
	 * Get blog attachments to process.
	 *
	 * @param int  $last_attachment_id
	 * @param int  $limit Maximum number of attachment IDs to return
	 * @param bool $count Just return the count, negates $limit, default false
	 *
	 * @return array|int
	 */
	protected function get_blog_attachments( $last_attachment_id, $limit, $count = false ) {
		return Media_Library_Item::get_source_ids( $last_attachment_id, $limit, $count );
	}

	/**
	 * Get blog last attachment ID.
	 *
	 * @return int
	 */
	protected function get_blog_last_attachment_id() {
		$attachments = $this->get_blog_attachments( null, 1 );

		return empty( $attachments ) ? 0 : reset( $attachments );
	}

	/**
	 * Have all blog attachments been processed?
	 *
	 * @param array $item
	 *
	 * @return bool
	 */
	protected function all_blog_attachments_processed( $item ) {
		foreach ( $item['blogs'] as $blog ) {
			if ( ! $blog['processed'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Record new error.
	 *
	 * @param int    $blog_id
	 * @param int    $attachment_id
	 * @param string $message
	 */
	protected function record_error( $blog_id, $attachment_id, $message ) {
		$this->errors[ $blog_id ][ $attachment_id ][] = $message;

		AS3CF_Error::log( $message );
	}

	/**
	 * How many attachments have had errors recorded by this process?
	 *
	 * @return int
	 */
	protected function count_errors() {
		$count = 0;

		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $blog_id => $errors ) {
				$count += count( $errors );
			}
		}

		return $count;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		$notice_id = $this->tool->get_tool_key() . '_completed';

		$this->as3cf->notices->undismiss_notice_for_all( $notice_id );
		$this->as3cf->notices->remove_notice_by_id( $notice_id );

		if ( $this->tool->get_errors() ) {
			$message = $this->get_complete_with_errors_message();
			$type    = 'notice-warning';
		} else {
			$message = $this->get_complete_message();
			$type    = 'updated';
		}

		$args = array(
			'custom_id'         => $notice_id,
			'type'              => $type,
			'flash'             => false,
			'only_show_to_user' => false,
		);

		$this->as3cf->notices->add_notice( $message, $args );
	}

	/**
	 * Adds a note about errors to completion message.
	 *
	 * @return string
	 */
	protected function get_complete_with_errors_message() {
		return $this->get_complete_message() . ' ' . __( 'Some errors were recorded.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Process attachments chunk.
	 *
	 * @param array $attachments
	 * @param int   $blog_id
	 *
	 * @return array
	 */
	abstract protected function process_attachments_chunk( $attachments, $blog_id );

	/**
	 * Get complete notice message.
	 *
	 * @return string
	 */
	abstract protected function get_complete_message();
}
