<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Dashboard {

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
	}

	public function register_widget() {
		wp_add_dashboard_widget(
			'distractinator_stats',
			'Distractinator — Quick Stats',
			[ $this, 'render' ]
		);
	}

	public function render() {
		$counts  = wp_count_posts( 'distractinator_site' );
		$publish = (int) ( $counts->publish ?? 0 );
		$pending = (int) ( $counts->pending ?? 0 );

		// Total clicks across all sites
		global $wpdb;
		$total_clicks = (int) $wpdb->get_var(
			"SELECT SUM(meta_value+0) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_distractinator_clicks'"
		);

		// Dead links
		$dead_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_distractinator_dead' AND meta_value = '1'"
		);

		// Most clicked site
		$top = get_posts( [
			'post_type'      => 'distractinator_site',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_distractinator_clicks',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		] );

		$top_title  = ! empty( $top ) ? get_the_title( $top[0] ) : '—';
		$top_clicks = ! empty( $top ) ? (int) get_post_meta( $top[0]->ID, '_distractinator_clicks', true ) : 0;
		?>
		<ul style="margin:0;padding:0;list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
			<?php $this->stat( 'Sites in Pool', $publish, '#0073aa' ); ?>
			<?php $this->stat( 'Total Clicks', number_format( $total_clicks ), '#00a32a' ); ?>
			<?php $this->stat( 'Pending Review', $pending, $pending > 0 ? '#dba617' : '#888' ); ?>
			<?php $this->stat( 'Dead Links', $dead_count, $dead_count > 0 ? '#d63638' : '#888' ); ?>
		</ul>

		<?php if ( ! empty( $top ) ) : ?>
		<div style="margin-top:14px;padding-top:12px;border-top:1px solid #eee;font-size:13px;color:#555;">
			<strong>Most visited:</strong>
			<a href="<?php echo esc_url( get_edit_post_link( $top[0]->ID ) ); ?>"><?php echo esc_html( $top_title ); ?></a>
			— <?php echo esc_html( number_format( $top_clicks ) ); ?> clicks
		</div>
		<?php endif; ?>

		<div style="margin-top:10px;text-align:right;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=distractinator' ) ); ?>" style="font-size:12px">Manage Sites &rarr;</a>
			<?php if ( $pending > 0 ) : ?>
			&nbsp;|&nbsp;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=distractinator-submissions' ) ); ?>" style="font-size:12px;color:#dba617">Review <?php echo esc_html( $pending ); ?> submission<?php echo $pending !== 1 ? 's' : ''; ?> &rarr;</a>
			<?php endif; ?>
		</div>
		<?php
	}

	private function stat( string $label, $value, string $color ) {
		?>
		<li style="background:#f6f7f7;border-radius:6px;padding:10px 14px;">
			<div style="font-size:22px;font-weight:700;color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $value ); ?></div>
			<div style="font-size:12px;color:#666;margin-top:2px"><?php echo esc_html( $label ); ?></div>
		</li>
		<?php
	}
}
