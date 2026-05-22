<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_API {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'wp_ajax_nopriv_distractinator_random', [ $this, 'ajax_random' ] );
		add_action( 'wp_ajax_distractinator_random', [ $this, 'ajax_random' ] );
		add_action( 'wp_ajax_nopriv_distractinator_submit', [ $this, 'ajax_submit' ] );
		add_action( 'wp_ajax_distractinator_submit', [ $this, 'ajax_submit' ] );
		add_action( 'wp_ajax_nopriv_distractinator_report', [ $this, 'ajax_report' ] );
		add_action( 'wp_ajax_distractinator_report', [ $this, 'ajax_report' ] );
	}

	public function register_routes() {
		register_rest_route( 'distractinator/v1', '/random', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_random' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function get_random( WP_REST_Request $request ) {
		$site = $this->fetch_random_site();
		if ( ! $site ) {
			return new WP_Error( 'no_sites', 'No distractinator sites available.', [ 'status' => 404 ] );
		}
		return rest_ensure_response( $site );
	}

	public function ajax_random() {
		check_ajax_referer( 'distractinator_nonce', 'nonce' );
		$site = $this->fetch_random_site();
		if ( ! $site ) {
			wp_send_json_error( 'No sites available.' );
		}
		wp_send_json_success( $site );
	}

	public function ajax_submit() {
		check_ajax_referer( 'distractinator_nonce', 'nonce' );

		if ( ! get_option( 'distractinator_allow_submissions', 1 ) ) {
			wp_send_json_error( 'Submissions are currently closed.' );
		}

		// Rate limit: max 3 submissions per IP per hour
		$ip_key = 'dz_submit_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$count  = (int) get_transient( $ip_key );
		if ( $count >= 3 ) {
			wp_send_json_error( 'You\'ve submitted too many sites recently. Please try again later.' );
		}
		set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

		$url   = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( 'Please enter a valid URL.' );
		}

		// Duplicate check
		$existing = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => [ 'publish', 'pending' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				[ 'key' => '_distractinator_url', 'value' => $url, 'compare' => '=' ],
			],
		] );
		if ( ! empty( $existing ) ) {
			wp_send_json_error( 'This URL has already been submitted.' );
		}

		$post_id = wp_insert_post( [
			'post_title'  => $title ?: parse_url( $url, PHP_URL_HOST ),
			'post_status' => 'pending',
			'post_type'   => 'distractinator_site',
			'meta_input'  => [
				'_distractinator_url'    => $url,
				'_distractinator_active' => 0,
			],
		] );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( 'Could not save submission.' );
		}

		wp_send_json_success( 'Thanks for your submission! We\'ll review it shortly.' );
	}

	public function ajax_report() {
		check_ajax_referer( 'distractinator_nonce', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || get_post_type( $id ) !== 'distractinator_site' ) {
			wp_send_json_error( 'Invalid site.' );
		}

		// Rate-limit: one report per IP per site per day using transients
		$ip_key = 'dz_report_' . md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '_' . $id );
		if ( get_transient( $ip_key ) ) {
			wp_send_json_success( 'Already reported. Thanks!' );
		}
		set_transient( $ip_key, 1, DAY_IN_SECONDS );

		$count = (int) get_post_meta( $id, '_distractinator_report_count', true );
		$count++;
		update_post_meta( $id, '_distractinator_report_count', $count );

		// Auto-flag as dead if 3+ unique IP reports
		if ( $count >= 3 ) {
			update_post_meta( $id, '_distractinator_dead', 1 );
			update_post_meta( $id, '_distractinator_dead_date', current_time( 'Y-m-d' ) );
		}

		wp_send_json_success( 'Thanks for reporting! We\'ll check this link.' );
	}

	private function fetch_random_site() {
		$posts = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => '_distractinator_active',
					'value'   => '1',
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_distractinator_dead',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_distractinator_dead',
						'value'   => '1',
						'compare' => '!=',
					],
				],
			],
			'fields' => 'ids',
		] );

		if ( empty( $posts ) ) {
			return null;
		}

		$id  = $posts[ array_rand( $posts ) ];
		$url = get_post_meta( $id, '_distractinator_url', true );

		// Increment click count
		$clicks = (int) get_post_meta( $id, '_distractinator_clicks', true );
		update_post_meta( $id, '_distractinator_clicks', $clicks + 1 );

		return [
			'id'    => $id,
			'title' => get_the_title( $id ),
			'url'   => $url,
		];
	}
}
