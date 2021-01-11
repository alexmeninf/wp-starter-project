<?php

namespace DeliciousBrains\WP_Offload_Media\Pro;

use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Pro\Background_Processes\Background_Tool_Process;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade;

abstract class Background_Tool extends Tool {

	/**
	 * @var string
	 */
	protected $type = 'background-tool';

	/**
	 * @var string
	 */
	protected $view = 'background-tool';

	/**
	 * @var Background_Tool_Process
	 */
	protected $background_process;

	protected $actions = array(
		'start',
		'pause_resume',
		'cancel',
	);

	/**
	 * Initialize the tool.
	 */
	public function init() {
		parent::init();

		// JS data
		add_filter( 'as3cfpro_js_nonces', array( $this, 'add_js_nonces' ) );
		add_action( "wp_ajax_as3cfpro_{$this->tool_key}_start", array( $this, 'ajax_handle_start' ) );
		add_action( "wp_ajax_as3cfpro_{$this->tool_key}_cancel", array( $this, 'ajax_handle_cancel' ) );
		add_action( "wp_ajax_as3cfpro_{$this->tool_key}_pause_resume", array( $this, 'ajax_handle_pause_resume' ) );

		$this->background_process = $this->get_background_process_class();

		// During an upgrade, cancel all background processes.
		if ( Upgrade::is_locked() && ( $this->is_processing() || $this->is_queued() ) ) {
			$this->handle_cancel();
		}

		$this->maybe_handle_action_url();
	}

	/**
	 * Get the sidebar notice details
	 *
	 * @return false|array
	 */
	protected function get_sidebar_block_args() {
		return $this->default_sidebar_block_args();
	}

	/**
	 * Get the default sidebar notice details
	 *
	 * @return false|array
	 */
	public function default_sidebar_block_args() {
		return array(
			'title'              => $this->get_title_text(),
			'more_info'          => $this->get_more_info_text(),
			'status_description' => $this->get_status_description(),
			'busy_description'   => $this->get_busy_description(),
			'button'             => $this->get_button_text(),
			'is_queued'          => $this->is_queued(),
			'is_paused'          => $this->is_paused(),
			'is_cancelled'       => $this->is_cancelled(),
			'is_upgrading'       => Upgrade::is_locked(),
			'total_progress'     => $this->get_progress(),
			'progress'           => $this->get_progress(),
			'queue'              => $this->get_queue_counts(),
		);
	}

	/**
	 * Get status.
	 *
	 * @return array
	 */
	public function get_status() {
		$status = array(
			'should_render'  => $this->should_render(),
			'total_progress' => $this->get_progress(),
			'progress'       => $this->get_progress(),
			'is_queued'      => $this->is_queued(),
			'is_processing'  => $this->is_processing(),
			'is_paused'      => $this->is_paused(),
			'is_cancelled'   => $this->is_cancelled(),
			'is_upgrading'   => Upgrade::is_locked(),
			'description'    => $this->get_status_description(),
			'queue'          => $this->get_queue_counts(),
		);

		$this->maybe_add_loopback_request_notice( $status );

		$status['notices'] = $this->get_notices();

		return $status;
	}

	/**
	 * If it looks like this tool is stuck, check loopback site health report and potentially add notice.
	 *
	 * @param array $status
	 */
	private function maybe_add_loopback_request_notice( $status ) {
		$site_health_path = trailingslashit( ABSPATH ) . 'wp-admin/includes/class-wp-site-health.php';

		if (
			! empty( $status['is_queued'] ) &&
			empty( $status['is_processing'] ) &&
			empty( $status['is_paused'] ) &&
			empty( $status['is_cancelled'] ) &&
			file_exists( $site_health_path ) &&
			(
				false === get_site_transient( $this->prefix . '_loopback_test' ) ||
				get_site_transient( $this->prefix . '_loopback_test' ) === $this->tool_key
			)
		) {
			set_site_transient( $this->prefix . '_loopback_test', $this->tool_key, 30 );

			/** @noinspection PhpIncludeInspection */
			require_once $site_health_path;
			$site_health = new \WP_Site_Health();

			$loopback = $site_health->get_test_loopback_requests();

			if (
				! empty( $loopback['status'] ) &&
				'good' !== $loopback['status'] &&
				! empty( $loopback['label'] ) &&
				! empty( $loopback['description'] ) ) {
				$args = array(
					'type'              => 'error',
					'class'             => 'tool-error',
					'dismissible'       => false,
					'flash'             => false,
					'only_show_to_user' => false,
					'only_show_on_tab'  => $this->tab,
					'custom_id'         => $this->errors_key_prefix . 'loopback_test',
					'user_capabilities' => array( 'as3cfpro', 'is_plugin_setup' ),
				);

				$site_health_link = get_dashboard_url( get_current_user_id(), 'site-health.php' );

				$doc_url  = $this->as3cf->dbrains_url( '/wp-offload-media/doc/background-processes-not-completing/', array(
					'utm_campaign' => 'support+docs',
				) );
				$doc_link = AS3CF_Utils::dbrains_link( $doc_url, __( 'Background Processes doc', 'amazon-s3-and-cloudfront' ) );

				$message = sprintf( __( 'The background process is stuck. Please ensure that the <strong>loopback request</strong> test is passing in <a href="%1$s">Site Health</a>.<br><br>For troubleshooting tips please see our %2$s.', 'amazon-s3-and-cloudfront' ), $site_health_link, $doc_link );

				$this->as3cf->notices->add_notice( $this->get_error_notice_message( $message ), $args );
			} else {
				$this->as3cf->notices->remove_notice_by_id( $this->errors_key_prefix . 'loopback_test' );
			}
		} elseif (
			false === get_site_transient( $this->prefix . '_loopback_test' ) ||
			get_site_transient( $this->prefix . '_loopback_test' ) === $this->tool_key
		) {
			// No other tool is stuck, clear out admin notice if set.
			$this->as3cf->notices->remove_notice_by_id( $this->errors_key_prefix . 'loopback_test' );
		}
	}

	/**
	 * Add general background tool notices, but allow child classes to inject custom notices to be updated in the DOM.
	 *
	 * @return array
	 */
	protected function get_custom_notices_to_update() {
		$notices = parent::get_custom_notices_to_update();

		$notice = $this->as3cf->notices->find_notice_by_id( $this->errors_key_prefix . 'loopback_test' );

		if ( ! empty( $notice ) ) {
			$notices[] = $notice;
		}

		return $notices;
	}

	/**
	 * Get more info text.
	 *
	 * @return string
	 */
	public function get_more_info_text() {
		return '';
	}

	/**
	 * Get status description.
	 *
	 * @return string
	 */
	public function get_status_description() {
		if ( $this->is_processing() && ( $this->is_cancelled() || $this->is_paused() ) ) {
			return __( 'Completing current batch.', 'amazon-s3-and-cloudfront' );
		}

		if ( $this->is_paused() ) {
			return __( 'Paused', 'amazon-s3-and-cloudfront' );
		}

		if ( $this->is_queued() ) {
			return $this->get_queued_status();
		}

		return '';
	}

	/**
	 * Get busy description.
	 *
	 * @return string
	 */
	public function get_busy_description() {
		return __( 'Initiating&hellip;', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Add the nonces to the JavaScript.
	 *
	 * @param array $js_nonces
	 *
	 * @return array
	 */
	public function add_js_nonces( $js_nonces ) {
		$js_nonces['tools'][ $this->tool_key ]            = $this->create_tool_nonces( $this->actions );
		$js_nonces[ 'dismiss_errors_' . $this->tool_key ] = wp_create_nonce( 'dismiss-errors-' . $this->tool_slug );

		return $js_nonces;
	}

	/**
	 * Create tool nonces.
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	protected function create_tool_nonces( $actions ) {
		$nonces = array();

		foreach ( $actions as $action ) {
			$nonces[ $action ] = wp_create_nonce( $this->tool_key . '_' . $action );
		}

		return $nonces;
	}

	/**
	 * Get a valid URL that may trigger the given action for the tool.
	 *
	 * @param string $action One of 'start', 'pause_resume' or 'cancel'.
	 *
	 * @return string
	 */
	protected function get_action_url( $action ) {
		if ( ! in_array( $action, $this->actions ) ) {
			return '';
		}

		return $this->as3cf->get_plugin_page_url(
			array(
				'tool'   => $this->tool_key,
				'action' => $action,
				'nonce'  => wp_create_nonce( $this->tool_key . '_' . $action ),
			)
		);
	}

	/**
	 * Check request for tool action and calls handler if all in order.
	 */
	protected function maybe_handle_action_url() {
		if (
			! empty( $_REQUEST['tool'] ) &&
			$_REQUEST['tool'] === $this->tool_key &&
			! empty( $_REQUEST['action'] ) &&
			in_array( $_REQUEST['action'], $this->actions ) &&
			! empty( $_REQUEST['nonce'] ) &&
			method_exists( $this, 'ajax_handle_' . $_REQUEST['action'] ) &&
			wp_verify_nonce( $_REQUEST['nonce'], $this->tool_key . '_' . $_REQUEST['action'] )
		) {
			call_user_func( array( $this, "handle_{$_REQUEST['action']}" ) );

			wp_redirect( $this->as3cf->get_plugin_page_url() );
		}
	}

	/**
	 * AJAX handle start.
	 */
	public function ajax_handle_start() {
		check_ajax_referer( $this->tool_key . '_start', 'nonce' );

		$this->handle_start();
	}

	/**
	 * Handle start.
	 */
	public function handle_start() {
		if ( $this->is_queued() ) {
			return;
		}

		$this->clear_errors();

		$session = $this->create_session();

		$this->background_process->push_to_queue( $session )->save()->dispatch();
	}

	/**
	 * AJAX handle cancel.
	 */
	public function ajax_handle_cancel() {
		check_ajax_referer( $this->tool_key . '_cancel', 'nonce' );

		$this->handle_cancel();
	}

	/**
	 * Handle cancel.
	 */
	public function handle_cancel() {
		if ( ! $this->is_queued() ) {
			return;
		}

		$this->background_process->cancel();
	}

	/**
	 * AJAX handle pause resume.
	 */
	public function ajax_handle_pause_resume() {
		check_ajax_referer( $this->tool_key . '_pause_resume', 'nonce' );

		$this->handle_pause_resume();
	}

	/**
	 * Handle pause resume.
	 */
	public function handle_pause_resume() {
		if ( ! $this->is_queued() || $this->is_cancelled() ) {
			return;
		}

		if ( $this->is_paused() ) {
			$this->background_process->resume();
		} else {
			$this->background_process->pause();
		}
	}

	/**
	 * Create session.
	 *
	 * @return array
	 */
	protected function create_session() {
		$session = array(
			'total_attachments'     => 0,
			'processed_attachments' => 0,
			'blogs_processed'       => false,
			'blogs'                 => array(),
		);

		foreach ( $this->as3cf->get_all_blog_table_prefixes() as $blog_id => $prefix ) {
			$session['blogs'][ $blog_id ] = array(
				'prefix'             => $prefix,
				'processed'          => false,
				'total_attachments'  => null,
				'last_attachment_id' => null,
			);
		}

		return $session;
	}

	/**
	 * Get progress.
	 *
	 * @return int
	 */
	public function get_progress() {
		$batch = $this->get_batch();

		if ( empty( $batch ) ) {
			return 0;
		}

		$data = $batch->data;
		$data = array_shift( $data );

		if ( empty( $data['total_attachments'] ) || ! isset( $data['processed_attachments'] ) ) {
			return 0;
		}

		return absint( $data['processed_attachments'] / $data['total_attachments'] * 100 );
	}

	/**
	 * Is queued?
	 *
	 * @return bool
	 */
	public function is_queued() {
		$batch = $this->get_batch();

		if ( empty( $batch ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get total and processed counts for queue.
	 *
	 * @return array
	 */
	public function get_queue_counts() {
		$counts = array(
			'total'     => 0,
			'processed' => 0,
		);

		$batch = $this->get_batch();

		if ( empty( $batch ) ) {
			return $counts;
		}

		$data = $batch->data;
		$data = array_shift( $data );

		if ( ! isset( $data['total_attachments'] ) || ! isset( $data['processed_attachments'] ) ) {
			return $counts;
		}

		$counts['total']     = $data['total_attachments'];
		$counts['processed'] = $data['processed_attachments'];

		return $counts;
	}

	/**
	 * Is the tool paused?
	 *
	 * @return bool
	 */
	public function is_paused() {
		return $this->background_process->is_paused();
	}

	/**
	 * Has the tool been cancelled?
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		return $this->background_process->is_cancelled();
	}

	/**
	 * Is the background process currently running?
	 *
	 * @return bool
	 */
	public function is_processing() {
		return $this->background_process->is_process_running();
	}

	/**
	 * Get background process batch.
	 *
	 * @return array
	 */
	protected function get_batch() {
		static $batch;

		if ( is_null( $batch ) ) {
			$batch = $this->background_process->get_batches( 1 );

			if ( empty( $batch ) ) {
				$batch = array();
			} else {
				$batch = array_shift( $batch );
			}
		}

		return $batch;
	}

	/**
	 * Get title text.
	 *
	 * @return string
	 */
	abstract public function get_title_text();

	/**
	 * Get button text.
	 *
	 * @return string
	 */
	abstract public function get_button_text();

	/**
	 * Get queued status text.
	 *
	 * @return string
	 */
	abstract public function get_queued_status();

	/**
	 * Get background process class.
	 *
	 * @return Background_Tool_Process|null
	 */
	abstract protected function get_background_process_class();

}