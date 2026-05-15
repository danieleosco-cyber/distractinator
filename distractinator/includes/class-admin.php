<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );
		add_action( 'save_post_distractinator_site', [ $this, 'save_meta' ] );
		add_action( 'admin_post_distractinator_approve', [ $this, 'approve_submission' ] );
		add_action( 'admin_post_distractinator_reject', [ $this, 'reject_submission' ] );
		add_action( 'admin_notices', [ $this, 'pending_notice' ] );
		add_action( 'admin_notices', [ $this, 'import_notice' ] );
		add_filter( 'manage_distractinator_site_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_distractinator_site_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
	}

	public function menu() {
		add_menu_page(
			'Distractinator',
			'Distractinator',
			'manage_options',
			'distractinator',
			[ $this, 'page_sites' ],
			'dashicons-randomize',
			30
		);

		add_submenu_page(
			'distractinator',
			'All Sites',
			'All Sites',
			'manage_options',
			'distractinator',
			[ $this, 'page_sites' ]
		);

		add_submenu_page(
			'distractinator',
			'Add New Site',
			'Add New Site',
			'manage_options',
			'post-new.php?post_type=distractinator_site'
		);

		add_submenu_page(
			'distractinator',
			'Submissions',
			'Submissions <span class="awaiting-mod" id="distractinator-pending-count"></span>',
			'manage_options',
			'distractinator-submissions',
			[ $this, 'page_submissions' ]
		);

		add_submenu_page(
			'distractinator',
			'Settings',
			'Settings',
			'manage_options',
			'distractinator-settings',
			[ $this, 'page_settings' ]
		);
	}

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
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$args = [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			's'              => $search,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query = new WP_Query( $args );
		?>
		<form method="get">
			<input type="hidden" name="page" value="distractinator">
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search sites…">
				<input type="submit" class="button" value="Search">
			</p>
		</form>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Title</th>
					<th>URL</th>
					<th>Clicks</th>
					<th>Status</th>
					<th>Dead Link</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); ?>
				<?php
				$id      = get_the_ID();
				$url     = get_post_meta( $id, '_distractinator_url', true );
				$clicks  = (int) get_post_meta( $id, '_distractinator_clicks', true );
				$dead    = get_post_meta( $id, '_distractinator_dead', true );
				$active  = get_post_meta( $id, '_distractinator_active', true );
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php the_title(); ?></a></td>
					<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
					<td><?php echo esc_html( $clicks ); ?></td>
					<td><?php echo $active ? '<span style="color:green">Active</span>' : '<span style="color:gray">Inactive</span>'; ?></td>
					<td><?php echo $dead ? '<span style="color:red">Dead</span>' : '&mdash;'; ?></td>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Edit</a> |
						<a href="<?php echo esc_url( get_delete_post_link( $id ) ); ?>" onclick="return confirm('Delete this site?')">Delete</a>
					</td>
				</tr>
			<?php endwhile; wp_reset_postdata(); else : ?>
				<tr><td colspan="6">No sites found.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		$total = $query->found_posts;
		$pages = ceil( $total / 20 );
		if ( $pages > 1 ) {
			echo paginate_links( [
				'base'    => add_query_arg( 'paged', '%#%' ),
				'total'   => $pages,
				'current' => $paged,
			] );
		}
	}

	public function page_submissions() {
		$submissions = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'pending',
			'posts_per_page' => 50,
		] );
		?>
		<div class="wrap">
			<h1>Pending Submissions</h1>
			<?php if ( empty( $submissions ) ) : ?>
				<p>No pending submissions.</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Title</th>
						<th>URL</th>
						<th>Submitted</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $submissions as $post ) : ?>
					<?php $url = get_post_meta( $post->ID, '_distractinator_url', true ); ?>
					<tr>
						<td><?php echo esc_html( $post->post_title ); ?></td>
						<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
						<td><?php echo esc_html( get_the_date( 'Y-m-d', $post ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=distractinator_approve&id=' . $post->ID ), 'distractinator_approve_' . $post->ID ) ); ?>" class="button button-primary">Approve</a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=distractinator_reject&id=' . $post->ID ), 'distractinator_reject_' . $post->ID ) ); ?>" class="button" onclick="return confirm('Reject and delete this submission?')">Reject</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function page_settings() {
		if ( isset( $_POST['distractinator_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['distractinator_settings_nonce'] ) ), 'distractinator_save_settings' ) ) {
			update_option( 'distractinator_button_text', sanitize_text_field( wp_unslash( $_POST['button_text'] ?? 'Distract Me!' ) ) );
			update_option( 'distractinator_heading', sanitize_text_field( wp_unslash( $_POST['heading'] ?? 'The Distractinator' ) ) );
			update_option( 'distractinator_subheading', sanitize_text_field( wp_unslash( $_POST['subheading'] ?? 'Because the internet doesn\'t need a reason.' ) ) );
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
					<tr>
						<th><label for="heading">Page Heading</label></th>
						<td><input type="text" id="heading" name="heading" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_heading', 'The Distractinator' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="subheading">Subheading</label></th>
						<td><input type="text" id="subheading" name="subheading" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_subheading', "Because the internet doesn't need a reason." ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="button_text">Button Text</label></th>
						<td><input type="text" id="button_text" name="button_text" class="regular-text" value="<?php echo esc_attr( get_option( 'distractinator_button_text', 'Distract Me!' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="bg_color">Background Color</label></th>
						<td><input type="color" id="bg_color" name="bg_color" value="<?php echo esc_attr( get_option( 'distractinator_bg_color', '#1a1a2e' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="btn_color">Button Color</label></th>
						<td><input type="color" id="btn_color" name="btn_color" value="<?php echo esc_attr( get_option( 'distractinator_btn_color', '#e94560' ) ); ?>"></td>
					</tr>
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

	public function approve_submission() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'distractinator_approve_' . $id ) ) {
			wp_die( 'Invalid request.' );
		}
		wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
		update_post_meta( $id, '_distractinator_active', 1 );
		wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&approved=1' ) );
		exit;
	}

	public function reject_submission() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'distractinator_reject_' . $id ) ) {
			wp_die( 'Invalid request.' );
		}
		wp_delete_post( $id, true );
		wp_redirect( admin_url( 'admin.php?page=distractinator-submissions&rejected=1' ) );
		exit;
	}

	public function pending_notice() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'distractinator' ) === false ) {
			return;
		}
		$count = count( get_posts( [ 'post_type' => 'distractinator_site', 'post_status' => 'pending', 'posts_per_page' => -1 ] ) );
		if ( $count > 0 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $count ) . ' site submission(s) awaiting review. <a href="' . esc_url( admin_url( 'admin.php?page=distractinator-submissions' ) ) . '">Review now</a></p></div>';
		}
	}

	public function import_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_distractinator' ) {
			return;
		}
		if ( isset( $_GET['import_done'] ) ) {
			$imported = absint( $_GET['import_imported'] ?? 0 );
			$skipped  = absint( $_GET['import_skipped'] ?? 0 );
			$errors   = absint( $_GET['import_errors'] ?? 0 );
			echo '<div class="notice notice-success is-dismissible"><p>Import complete: <strong>' . esc_html( $imported ) . '</strong> imported, <strong>' . esc_html( $skipped ) . '</strong> skipped (duplicates), <strong>' . esc_html( $errors ) . '</strong> errors.</p></div>';
		}
		if ( isset( $_GET['import_error'] ) ) {
			echo '<div class="notice notice-error"><p>Import failed: ' . esc_html( $_GET['import_error'] ) . '</p></div>';
		}
	}

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
			<tr>
				<th><label for="distractinator_url">URL</label></th>
				<td><input type="url" id="distractinator_url" name="distractinator_url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="distractinator_active">Active</label></th>
				<td><input type="checkbox" id="distractinator_active" name="distractinator_active" value="1" <?php checked( $active, '1' ); ?>>
				<span class="description">Uncheck to exclude from the random pool.</span></td>
			</tr>
			<tr>
				<th>Clicks</th>
				<td><?php echo esc_html( $clicks ); ?></td>
			</tr>
			<?php if ( $dead ) : ?>
			<tr>
				<th>Dead Link</th>
				<td><span style="color:red">Flagged as dead on <?php echo esc_html( get_post_meta( $post->ID, '_distractinator_dead_date', true ) ); ?></span></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['distractinator_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['distractinator_meta_nonce'] ) ), 'distractinator_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['distractinator_url'] ) ) {
			update_post_meta( $post_id, '_distractinator_url', esc_url_raw( wp_unslash( $_POST['distractinator_url'] ) ) );
		}
		update_post_meta( $post_id, '_distractinator_active', isset( $_POST['distractinator_active'] ) ? 1 : 0 );
		// Clear dead flag if URL was updated
		delete_post_meta( $post_id, '_distractinator_dead' );
		delete_post_meta( $post_id, '_distractinator_dead_date' );
	}

	public function columns( $columns ) {
		return [
			'cb'             => $columns['cb'],
			'title'          => 'Title',
			'distractinator_url'    => 'URL',
			'distractinator_clicks' => 'Clicks',
			'distractinator_active' => 'Active',
			'distractinator_dead'   => 'Dead Link',
			'date'           => 'Added',
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
}
