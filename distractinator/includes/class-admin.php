<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Admin {

	private const PER_PAGE = 200;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );
		add_action( 'save_post_distractinator_site', [ $this, 'save_meta' ] );
		add_action( 'admin_post_distractinator_approve', [ $this, 'approve_submission' ] );
		add_action( 'admin_post_distractinator_reject', [ $this, 'reject_submission' ] );
		add_action( 'admin_post_distractinator_mark_live', [ $this, 'handle_mark_live' ] );
		add_action( 'admin_post_distractinator_bulk_sites', [ $this, 'handle_bulk_sites' ] );
		add_action( 'admin_post_distractinator_bulk_submissions', [ $this, 'handle_bulk_submissions' ] );
		add_action( 'admin_notices', [ $this, 'pending_notice' ] );
		add_action( 'admin_notices', [ $this, 'import_notice' ] );
		add_action( 'admin_notices', [ $this, 'bulk_notice' ] );
		add_filter( 'manage_distractinator_site_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_distractinator_site_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function menu() {
		add_menu_page( 'Distractinator', 'Distractinator', 'manage_options', 'distractinator', [ $this, 'page_sites' ], 'dashicons-randomize', 30 );
		add_submenu_page( 'distractinator', 'All Sites', 'All Sites', 'manage_options', 'distractinator', [ $this, 'page_sites' ] );
		add_submenu_page( 'distractinator', 'Add New Site', 'Add New Site', 'manage_options', 'post-new.php?post_type=distractinator_site' );
		add_submenu_page( 'distractinator', 'Submissions', 'Submissions', 'manage_options', 'distractinator-submissions', [ $this, 'page_submissions' ] );
		add_submenu_page( 'distractinator', 'Settings', 'Settings', 'manage_options', 'distractinator-settings', [ $this, 'page_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Sites page
	// -------------------------------------------------------------------------

	public function page_sites() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Distractinator Sites
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=distractinator_site' ) ); ?>" class="page-title-action">Add New</a>
			</h1>
			<?php
			$counts = wp_count_posts( 'distractinator_site' );
			echo '<p>' . esc_html( $counts->publish ) . ' published sites in the pool.</p>';
			$this->render_import_form();
			$this->render_sites_table();
			?>
		</div>
		<?php
	}

	private function render_import_form() {
		?>
		<details style="margin-bottom:1rem;background:#f6f7f7;padding:12px 16px;border-radius:4px;border:1px solid #ddd;">
			<summary style="cursor:pointer;font-weight:600;">Bulk Import via CSV</summary>
			<p style="margin:.75rem 0 .25rem">Upload a <code>.csv</code> file with columns: <code>url</code> (required), <code>title</code> (optional). First row must be headers. Duplicates are skipped automatically.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'distractinator_import' ); ?>
				<input type="hidden" name="action" value="distractinator_import_csv">
				<input type="file" name="distractinator_csv" accept=".csv,text/csv" required>
				<input type="submit" class="button button-primary" value="Import" style="margin-left:8px">
			</form>
		</details>
		<?php
	}

	private function render_sites_table() {
		$paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'date';
		$order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		$meta_orderby_map = [
			'clicks' => '_distractinator_clicks',
			'active' => '_distractinator_active',
			'dead'   => '_distractinator_dead',
		];

		$args = [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			's'              => $search,
			'order'          => $order,
		];

		if ( isset( $meta_orderby_map[ $orderby ] ) ) {
			$args['meta_key'] = $meta_orderby_map[ $orderby ];
			$args['orderby']  = $orderby === 'clicks' ? 'meta_value_num' : 'meta_value';
		} else {
			$args['orderby'] = 'date';
		}

		$query = new WP_Query( $args );
		$base_url = admin_url( 'admin.php?page=distractinator' );
		if ( $search ) $base_url = add_query_arg( 's', urlencode( $search ), $base_url );
		?>
		<form method="get" style="margin-bottom:8px">
			<input type="hidden" name="page" value="distractinator">
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search sites…">
				<input type="submit" class="button" value="Search">
			</p>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'distractinator_bulk_sites' ); ?>
			<input type="hidden" name="action" value="distractinator_bulk_sites">
			<?php if ( $search ) : ?><input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>"><?php endif; ?>

			<?php $this->render_bulk_bar( 'sites' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" id="dz-check-all-sites"></td>
						<th>Title</th>
						<th>URL</th>
						<?php echo $this->sort_th( 'Clicks', 'clicks', $orderby, $order, $base_url ); ?>
						<?php echo $this->sort_th( 'Status', 'active', $orderby, $order, $base_url ); ?>
						<?php echo $this->sort_th( 'Dead Link', 'dead', $orderby, $order, $base_url ); ?>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>
					<?php
					$id     = get_the_ID();
					$url    = get_post_meta( $id, '_distractinator_url', true );
					$clicks = (int) get_post_meta( $id, '_distractinator_clicks', true );
					$dead   = get_post_meta( $id, '_distractinator_dead', true );
					$active = get_post_meta( $id, '_distractinator_active', true );
					?>
					<tr>
						<th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $id ); ?>"></th>
						<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php the_title(); ?></a></td>
						<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
						<td><?php echo esc_html( $clicks ); ?></td>
						<td><?php echo $active ? '<span style="color:green">Active</span>' : '<span style="color:gray">Inactive</span>'; ?></td>
						<td><?php echo $dead ? '<span style="color:red">Dead</span>' : '&mdash;'; ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Edit</a> |
							<a href="<?php echo esc_url( get_delete_post_link( $id ) ); ?>" onclick="return confirm('Delete this site?')">Delete</a>
							<?php if ( $dead ) : ?>
							| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=distractinator_mark_live&id=' . $id ), 'distractinator_mark_live_' . $id ) ); ?>" style="color:green">Mark as Live</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; wp_reset_postdata(); else : ?>
					<tr><td colspan="7">No sites found.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<?php $this->render_bulk_bar( 'sites' ); ?>
		</form>

		<?php
		$total = $query->found_posts;
		$pages = ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo paginate_links( [
				'base'    => add_query_arg( 'paged', '%#%', $base_url ),
				'total'   => $pages,
				'current' => $paged,
			] );
		}
	}

	// -------------------------------------------------------------------------
	// Submissions page
	// -------------------------------------------------------------------------

	public function page_submissions() {
		$paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'date';
		$order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		$args = [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'pending',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'order'          => $order,
		];

		if ( $orderby === 'url' ) {
			$args['meta_key'] = '_distractinator_url';
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = 'date';
		}

		$query    = new WP_Query( $args );
		$base_url = admin_url( 'admin.php?page=distractinator-submissions' );
		?>
		<div class="wrap">
			<h1>Pending Submissions</h1>

			<?php if ( ! $query->have_posts() ) : ?>
				<p>No pending submissions.</p>
			<?php else : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'distractinator_bulk_submissions' ); ?>
				<input type="hidden" name="action" value="distractinator_bulk_submissions">

				<?php $this->render_bulk_bar( 'submissions' ); ?>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="dz-check-all-subs"></td>
							<th>Title</th>
							<?php echo $this->sort_th( 'URL', 'url', $orderby, $order, $base_url ); ?>
							<?php echo $this->sort_th( 'Submitted', 'date', $orderby, $order, $base_url ); ?>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php
						$id  = get_the_ID();
						$url = get_post_meta( $id, '_distractinator_url', true );
						?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $id ); ?>"></th>
							<td><?php the_title(); ?></td>
							<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
							<td><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=distractinator_approve&id=' . $id ), 'distractinator_approve_' . $id ) ); ?>" class="button button-primary">Approve</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=distractinator_reject&id=' . $id ), 'distractinator_reject_' . $id ) ); ?>" class="button" onclick="return confirm('Reject and delete?')">Reject</a>
							</td>
						</tr>
					<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>

				<?php $this->render_bulk_bar( 'submissions' ); ?>
			</form>

			<?php
			$total = $query->found_posts;
			$pages = ceil( $total / self::PER_PAGE );
			if ( $pages > 1 ) {
				echo paginate_links( [
					'base'    => add_query_arg( 'paged', '%#%', $base_url ),
					'total'   => $pages,
					'current' => $paged,
				] );
			}
			?>
			<?php endif; ?>
		</div>

		<script>
		document.getElementById('dz-check-all-subs')?.addEventListener('change', function() {
			document.querySelectorAll('input[name="post_ids[]"]').forEach(cb => cb.checked = this.checked);
		});
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Bulk action handlers
	// -------------------------------------------------------------------------

	public function handle_mark_live() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'distractinator_mark_live_' . $id ) ) {
			wp_die( 'Invalid request.' );
		}
		if ( get_post_type( $id ) !== 'distractinator_site' ) wp_die( 'Invalid site.' );
		delete_post_meta( $id, '_distractinator_dead' );
		delete_post_meta( $id, '_distractinator_dead_date' );
		delete_post_meta( $id, '_distractinator_report_count' );
		wp_redirect( admin_url( 'admin.php?page=distractinator&bulk_msg=live&bulk_count=1' ) );
		exit;
	}

	public function handle_bulk_sites() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
		check_admin_referer( 'distractinator_bulk_sites' );

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$ids         = array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) );

		if ( empty( $ids ) || ! in_array( $bulk_action, [ 'delete', 'mark_live' ], true ) ) {
			wp_redirect( admin_url( 'admin.php?page=distractinator&bulk_msg=none' ) );
			exit;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== 'distractinator_site' ) continue;
			if ( $bulk_action === 'delete' ) {
				if ( wp_delete_post( $id, true ) ) $count++;
			} elseif ( $bulk_action === 'mark_live' ) {
				delete_post_meta( $id, '_distractinator_dead' );
				delete_post_meta( $id, '_distractinator_dead_date' );
				delete_post_meta( $id, '_distractinator_report_count' );
				$count++;
			}
		}

		$msg = $bulk_action === 'delete' ? 'deleted' : 'live';
		wp_redirect( admin_url( 'admin.php?page=distractinator&bulk_msg=' . $msg . '&bulk_count=' . $count ) );
		exit;
	}

	public function handle_bulk_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
		check_admin_referer( 'distractinator_bulk_submissions' );

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$ids         = array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) );

		if ( empty( $ids ) || ! in_array( $bulk_action, [ 'approve', 'reject' ], true ) ) {
			wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&bulk_msg=none' ) );
			exit;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== 'distractinator_site' ) continue;
			if ( $bulk_action === 'approve' ) {
				wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
				update_post_meta( $id, '_distractinator_active', 1 );
			} else {
				wp_delete_post( $id, true );
			}
			$count++;
		}

		wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&bulk_msg=' . $bulk_action . '&bulk_count=' . $count ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------------

	public function page_settings() {
		if ( isset( $_POST['distractinator_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['distractinator_settings_nonce'] ) ), 'distractinator_save_settings' ) ) {
			update_option( 'distractinator_button_text', sanitize_text_field( wp_unslash( $_POST['button_text'] ?? 'Distract Me!' ) ) );
			update_option( 'distractinator_heading', sanitize_text_field( wp_unslash( $_POST['heading'] ?? 'The Distractinator' ) ) );
			update_option( 'distractinator_subheading', sanitize_text_field( wp_unslash( $_POST['subheading'] ?? "Because the internet doesn't need a reason." ) ) );
			update_option( 'distractinator_bg_color', sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '#1a1a2e' ) ) );
			update_option( 'distractinator_btn_color', sanitize_hex_color( wp_unslash( $_POST['btn_color'] ?? '#e94560' ) ) );
			update_option( 'distractinator_allow_submissions', isset( $_POST['allow_submissions'] ) ? 1 : 0 );
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>Distractinator Settings</h1>
			<form method="post">
				<?php wp_nonce_field( 'distractinator_save_settings', 'distractinator_settings_nonce' ); ?>
				<table class="form-table">
					<tr><th><label for="heading">Page Heading</label></th><td><input type="text" id="heading" name="heading" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_heading', 'The Distractinator' ) ); ?>"></td></tr>
					<tr><th><label for="subheading">Subheading</label></th><td><input type="text" id="subheading" name="subheading" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_subheading', "Because the internet doesn't need a reason." ) ); ?>"></td></tr>
					<tr><th><label for="button_text">Button Text</label></th><td><input type="text" id="button_text" name="button_text" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_button_text', 'Distract Me!' ) ); ?>"></td></tr>
					<tr><th><label for="bg_color">Background Color</label></th><td><input type="color" id="bg_color" name="bg_color" value="<?php echo esc_attr( get_option( 'distractinator_bg_color', '#1a1a2e' ) ); ?>"></td></tr>
					<tr><th><label for="btn_color">Button Color</label></th><td><input type="color" id="btn_color" name="btn_color" value="<?php echo esc_attr( get_option( 'distractinator_btn_color', '#e94560' ) ); ?>"></td></tr>
					<tr>
						<th><label for="allow_submissions">Allow Public Submissions</label></th>
						<td><input type="checkbox" id="allow_submissions" name="allow_submissions" value="1" <?php checked( get_option( 'distractinator_allow_submissions', 1 ) ); ?>>
						<span class="description">Show a submission form on the page.</span></td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Single approve / reject
	// -------------------------------------------------------------------------

	public function approve_submission() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'distractinator_approve_' . $id ) ) wp_die( 'Invalid request.' );
		wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
		update_post_meta( $id, '_distractinator_active', 1 );
		wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&approved=1' ) );
		exit;
	}

	public function reject_submission() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'distractinator_reject_' . $id ) ) wp_die( 'Invalid request.' );
		wp_delete_post( $id, true );
		wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&rejected=1' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Notices
	// -------------------------------------------------------------------------

	public function pending_notice() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'distractinator' ) === false ) return;
		$count = count( get_posts( [ 'post_type' => 'distractinator_site', 'post_status' => 'pending', 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
		if ( $count > 0 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $count ) . ' submission(s) awaiting review. <a href="' . esc_url( admin_url( 'admin.php?page=distractinator-submissions' ) ) . '">Review now</a></p></div>';
		}
	}

	public function import_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_distractinator' ) return;
		if ( isset( $_GET['import_done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Import complete: <strong>' . absint( $_GET['import_imported'] ?? 0 ) . '</strong> imported, <strong>' . absint( $_GET['import_skipped'] ?? 0 ) . '</strong> skipped, <strong>' . absint( $_GET['import_errors'] ?? 0 ) . '</strong> errors.</p></div>';
		}
		if ( isset( $_GET['import_error'] ) ) {
			echo '<div class="notice notice-error"><p>Import failed: ' . esc_html( $_GET['import_error'] ) . '</p></div>';
		}
	}

	public function bulk_notice() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'distractinator' ) === false ) return;

		if ( ! isset( $_GET['bulk_msg'] ) || $_GET['bulk_msg'] === 'none' ) return;

		$msg   = sanitize_key( $_GET['bulk_msg'] );
		$count = absint( $_GET['bulk_count'] ?? 0 );

		$text = match ( $msg ) {
			'deleted'  => $count . ' site(s) permanently deleted.',
			'live'     => $count . ' site(s) marked as live and re-added to the pool.',
			'approve'  => $count . ' submission(s) approved and published.',
			'reject'   => $count . ' submission(s) rejected and deleted.',
			default    => '',
		};

		if ( $text ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Meta boxes
	// -------------------------------------------------------------------------

	public function meta_boxes() {
		add_meta_box( 'distractinator_site_details', 'Site Details', [ $this, 'render_meta_box' ], 'distractinator_site', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'distractinator_save_meta', 'distractinator_meta_nonce' );
		$url    = get_post_meta( $post->ID, '_distractinator_url', true );
		$active = get_post_meta( $post->ID, '_distractinator_active', true );
		$clicks = (int) get_post_meta( $post->ID, '_distractinator_clicks', true );
		$dead   = get_post_meta( $post->ID, '_distractinator_dead', true );
		?>
		<table class="form-table">
			<tr><th><label for="distractinator_url">URL</label></th><td><input type="url" id="distractinator_url" name="distractinator_url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" required></td></tr>
			<tr><th><label for="distractinator_active">Active</label></th><td><input type="checkbox" id="distractinator_active" name="distractinator_active" value="1" <?php checked( $active, '1' ); ?>><span class="description"> Uncheck to exclude from the random pool.</span></td></tr>
			<tr><th>Clicks</th><td><?php echo esc_html( $clicks ); ?></td></tr>
			<?php if ( $dead ) : ?>
			<tr><th>Dead Link</th><td><span style="color:red">Flagged as dead on <?php echo esc_html( get_post_meta( $post->ID, '_distractinator_dead_date', true ) ); ?></span></td></tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['distractinator_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['distractinator_meta_nonce'] ) ), 'distractinator_save_meta' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( isset( $_POST['distractinator_url'] ) ) {
			update_post_meta( $post_id, '_distractinator_url', esc_url_raw( wp_unslash( $_POST['distractinator_url'] ) ) );
		}
		update_post_meta( $post_id, '_distractinator_active', isset( $_POST['distractinator_active'] ) ? 1 : 0 );
		delete_post_meta( $post_id, '_distractinator_dead' );
		delete_post_meta( $post_id, '_distractinator_dead_date' );
	}

	// -------------------------------------------------------------------------
	// CPT columns (for native post list — kept for compatibility)
	// -------------------------------------------------------------------------

	public function columns( $columns ) {
		return [
			'cb'                     => $columns['cb'],
			'title'                  => 'Title',
			'distractinator_url'     => 'URL',
			'distractinator_clicks'  => 'Clicks',
			'distractinator_active'  => 'Active',
			'distractinator_dead'    => 'Dead Link',
			'date'                   => 'Added',
		];
	}

	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'distractinator_url':
				$url = get_post_meta( $post_id, '_distractinator_url', true );
				echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
				break;
			case 'distractinator_clicks':
				echo esc_html( (int) get_post_meta( $post_id, '_distractinator_clicks', true ) );
				break;
			case 'distractinator_active':
				echo get_post_meta( $post_id, '_distractinator_active', true ) ? '<span style="color:green">Yes</span>' : '<span style="color:gray">No</span>';
				break;
			case 'distractinator_dead':
				echo get_post_meta( $post_id, '_distractinator_dead', true ) ? '<span style="color:red">Dead</span>' : '&mdash;';
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function sort_th( string $label, string $key, string $current_orderby, string $current_order, string $base_url ): string {
		$is_active  = $current_orderby === $key;
		$next_order = ( $is_active && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
		$url        = add_query_arg( [ 'orderby' => $key, 'order' => $next_order ], $base_url );
		$arrow      = '';
		if ( $is_active ) {
			$arrow = $current_order === 'ASC' ? ' &#9650;' : ' &#9660;';
		}
		return '<th class="sortable"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a></th>';
	}

	private function render_bulk_bar( string $context ): void {
		if ( $context === 'sites' ) {
			$options = '<option value="">Bulk Actions</option><option value="mark_live">Mark as Live</option><option value="delete">Delete</option>';
		} else {
			$options = '<option value="">Bulk Actions</option><option value="approve">Approve</option><option value="reject">Reject</option>';
		}
		$check_all_id = $context === 'sites' ? 'dz-check-all-sites' : 'dz-check-all-subs';
		?>
		<div class="tablenav top" style="margin:6px 0">
			<div class="alignleft actions bulkactions">
				<select name="bulk_action"><?php echo $options; ?></select>
				<input type="submit" class="button action" value="Apply">
			</div>
		</div>
		<?php if ( $context === 'sites' ) : ?>
		<script>
		document.getElementById('<?php echo esc_js( $check_all_id ); ?>')?.addEventListener('change', function() {
			document.querySelectorAll('input[name="post_ids[]"]').forEach(cb => cb.checked = this.checked);
		});
		</script>
		<?php endif; ?>
		<?php
	}
}
