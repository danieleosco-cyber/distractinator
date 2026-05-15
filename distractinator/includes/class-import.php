<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Import {

	public function __construct() {
		add_action( 'admin_post_distractinator_import_csv', [ $this, 'handle_import' ] );
	}

	/**
	 * Process uploaded CSV. Expected columns (order-independent, header row required):
	 *   url        — required
	 *   title      — optional, falls back to hostname
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'distractinator_import' );

		if ( empty( $_FILES['distractinator_csv']['tmp_name'] ) ) {
			wp_redirect( add_query_arg( 'import_error', 'no_file', admin_url( 'admin.php?page=distractinator' ) ) );
			exit;
		}

		$file = $_FILES['distractinator_csv']['tmp_name'];

		if ( ! is_readable( $file ) ) {
			wp_redirect( add_query_arg( 'import_error', 'unreadable', admin_url( 'admin.php?page=distractinator' ) ) );
			exit;
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			wp_redirect( add_query_arg( 'import_error', 'unreadable', admin_url( 'admin.php?page=distractinator' ) ) );
			exit;
		}

		$headers = null;
		$imported = 0;
		$skipped  = 0;
		$errors   = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			// First row — detect headers
			if ( $headers === null ) {
				$headers = array_map( 'strtolower', array_map( 'trim', $row ) );
				// If the first row looks like a URL, treat file as header-less (url, title order assumed)
				if ( filter_var( $headers[0], FILTER_VALIDATE_URL ) ) {
					$headers = [ 'url', 'title' ];
					// Re-process this row as data
					$data = array_combine(
						array_slice( $headers, 0, count( $row ) ),
						array_slice( $row, 0, count( $headers ) )
					);
					$result = $this->import_row( $data );
					if ( $result === 'imported' ) $imported++;
					elseif ( $result === 'skipped' ) $skipped++;
					else $errors++;
				}
				continue;
			}

			if ( count( $row ) < 1 ) continue;

			$data = array_combine(
				array_slice( $headers, 0, count( $row ) ),
				array_slice( $row, 0, count( $headers ) )
			);

			$result = $this->import_row( $data );
			if ( $result === 'imported' ) $imported++;
			elseif ( $result === 'skipped' ) $skipped++;
			else $errors++;
		}

		fclose( $handle );

		wp_redirect( add_query_arg( [
			'import_done'     => 1,
			'import_imported' => $imported,
			'import_skipped'  => $skipped,
			'import_errors'   => $errors,
		], admin_url( 'admin.php?page=distractinator' ) ) );
		exit;
	}

	private function import_row( array $data ): string {
		$url = isset( $data['url'] ) ? esc_url_raw( trim( $data['url'] ) ) : '';

		if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return 'error';
		}

		// Duplicate check
		$existing = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => [ 'publish', 'pending', 'draft' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				[ 'key' => '_distractinator_url', 'value' => $url, 'compare' => '=' ],
			],
			'fields' => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			return 'skipped';
		}

		$title = isset( $data['title'] ) && trim( $data['title'] ) !== ''
			? sanitize_text_field( trim( $data['title'] ) )
			: parse_url( $url, PHP_URL_HOST );

		$result = wp_insert_post( [
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'distractinator_site',
			'meta_input'  => [
				'_distractinator_url'    => $url,
				'_distractinator_clicks' => 0,
				'_distractinator_active' => 1,
			],
		] );

		return is_wp_error( $result ) ? 'error' : 'imported';
	}
}
