<?php

namespace DeliciousBrains\WP_Offload_Media\Pro\Integrations;

class Divi extends Integration {

	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public function is_installed() {
		// This integration fixes problems introduced by Divi Page Builder as used by the Divi and related themes.
		if ( defined( 'ET_BUILDER_VERSION' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Init integration.
	 */
	public function init() {
		add_filter( 'et_fb_load_raw_post_content', function ( $content ) {
			$content = apply_filters( 'as3cf_filter_post_local_to_s3', $content ); // Backwards compatibility

			return apply_filters( 'as3cf_filter_post_local_to_provider', $content );
		} );

		// Global Modules reset their filtered background image URLs, so let's fix that.
		if ( defined( 'ET_BUILDER_LAYOUT_POST_TYPE' ) ) {
			add_filter( 'the_posts', array( $this, 'the_posts' ), 10, 2 );
		}

		// The Divi Page Builder Gallery uses a non-standard and inherently anti-filter method of getting its editor thumbnails.
		if ( $this->doing_fetch_attachments() ) {
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		}
	}

	/**
	 * Is current request an et_fb_fetch_attachments AJAX call?
	 *
	 * @return bool
	 */
	private function doing_fetch_attachments() {
		if ( defined( 'DOING_AJAX' ) && ! empty( $_POST['action'] ) && 'et_fb_fetch_attachments' === $_POST['action'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Turn filtering on for WP_Query calls initiated by the et_fb_fetch_attachments AJAX call.
	 *
	 * @param \WP_Query $query
	 */
	public function pre_get_posts( \WP_Query $query ) {
		if ( ! empty( $query->query['post_type'] ) && 'attachment' === $query->query['post_type'] ) {
			$query->query_vars['suppress_filters'] = false;
		}
	}

	/**
	 * Handler for the 'the_posts' filter that runs local to provider URL filtering on Divi pages.
	 *
	 * @param array|\WP_Post $posts
	 * @param \WP_Query      $query
	 *
	 * @return array
	 */
	public function the_posts( $posts, $query ) {
		if (
			defined( 'ET_BUILDER_LAYOUT_POST_TYPE' ) &&
			! empty( $posts ) &&
			! empty( $query ) &&
			is_a( $query, 'WP_Query' ) &&
			! empty( $query->query_vars['post_type'] )
		) {
			if ( is_array( $posts ) ) {
				foreach ( $posts as $idx => $post ) {
					$posts[ $idx ] = $this->the_posts( $post, $query );
				}
			} elseif ( is_a( $posts, 'WP_Post' ) ) {
				$content_field = 'post_content';

				if ( $this->doing_fetch_attachments() && 'attachment' === $posts->post_type ) {
					$content_field = 'guid';
				}

				$content                 = apply_filters( 'as3cf_filter_post_local_to_s3', $posts->{$content_field} ); // Backwards compatibility
				$posts->{$content_field} = apply_filters( 'as3cf_filter_post_local_to_provider', $content );
			}
		}

		return $posts;
	}
}
