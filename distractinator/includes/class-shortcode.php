<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_Shortcode {

	public function __construct() {
		add_shortcode( 'distractinator', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
	}

	public function assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'distractinator' ) ) {
			return;
		}

		wp_enqueue_style(
			'distractinator',
			DISTRACTINATOR_URL . 'assets/css/frontend.css',
			[],
			DISTRACTINATOR_VERSION
		);

		wp_enqueue_script(
			'distractinator',
			DISTRACTINATOR_URL . 'assets/js/frontend.js',
			[ 'jquery' ],
			DISTRACTINATOR_VERSION,
			true
		);

		wp_localize_script( 'distractinator', 'distractinator', [
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'distractinator_nonce' ),
			'allowSubmissions' => (bool) get_option( 'distractinator_allow_submissions', 1 ),
		] );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( [
			'heading'    => get_option( 'distractinator_heading', 'The Distractinator' ),
			'subheading' => get_option( 'distractinator_subheading', "Because the internet doesn't need a reason." ),
			'button'     => get_option( 'distractinator_button_text', 'Distract Me!' ),
		], $atts, 'distractinator' );

		$bg_color  = get_option( 'distractinator_bg_color', '#1a1a2e' );
		$btn_color = get_option( 'distractinator_btn_color', '#e94560' );
		$allow_sub = get_option( 'distractinator_allow_submissions', 1 );

		ob_start();
		include DISTRACTINATOR_PATH . 'templates/distractinator.php';
		return ob_get_clean();
	}
}
