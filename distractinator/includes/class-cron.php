<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Cron {

	private const HOOK = 'distractinator_check_links';

	public function __construct() {
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function run() {
		$posts = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $posts as $id ) {
			$url = get_post_meta( $id, '_distractinator_url', true );
			if ( ! $url ) {
				continue;
			}

			$response = wp_remote_head( $url, [
				'timeout'    => 10,
				'user-agent' => 'DistractinatorBot/1.0',
				'sslverify'  => false,
			] );

			if ( is_wp_error( $response ) ) {
				$this->mark_dead( $id );
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 400 ) {
				$this->mark_dead( $id );
			} else {
				// Clear dead flag if it was previously dead
				delete_post_meta( $id, '_distractinator_dead' );
				delete_post_meta( $id, '_distractinator_dead_date' );
			}
		}
	}

	private function mark_dead( $post_id ) {
		update_post_meta( $post_id, '_distractinator_dead', 1 );
		update_post_meta( $post_id, '_distractinator_dead_date', current_time( 'Y-m-d' ) );
	}
}
