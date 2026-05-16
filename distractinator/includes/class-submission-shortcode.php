<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers [distractinator_submit] — a standalone submission form.
 * Can be placed on any page independently of the main [distractinator] shortcode.
 * The AJAX handler lives in Distractinator_API (ajax_submit) so no duplication.
 */
class Distractinator_Submission_Shortcode {

	public function __construct() {
		add_shortcode( 'distractinator_submit', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
	}

	public function assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'distractinator_submit' ) ) {
			return;
		}

		// Reuse the main frontend script & nonce — only enqueue if not already loaded
		if ( ! wp_script_is( 'distractinator', 'enqueued' ) ) {
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
				'allowSubmissions' => true,
			] );
		}

		wp_enqueue_style(
			'distractinator-submit-standalone',
			DISTRACTINATOR_URL . 'assets/css/submit-standalone.css',
			[],
			DISTRACTINATOR_VERSION
		);
	}

	public function render( $atts ) {
		if ( ! get_option( 'distractinator_allow_submissions', 1 ) ) {
			return '<p class="dz-submit-closed">Submissions are currently closed.</p>';
		}

		$atts = shortcode_atts( [
			'heading'     => 'Submit a Site',
			'subheading'  => 'Found something wonderfully useless? Share it.',
			'btn_label'   => 'Submit',
		], $atts, 'distractinator_submit' );

		ob_start();
		?>
		<div class="dz-submit-standalone">
			<?php if ( $atts['heading'] ) : ?>
				<h2 class="dz-submit-heading"><?php echo esc_html( $atts['heading'] ); ?></h2>
			<?php endif; ?>
			<?php if ( $atts['subheading'] ) : ?>
				<p class="dz-submit-subheading"><?php echo esc_html( $atts['subheading'] ); ?></p>
			<?php endif; ?>

			<form id="distractinator-form" class="dz-submit-form" novalidate>
				<label for="dz-url-standalone">Website URL <span aria-hidden="true">*</span></label>
				<input type="url" id="dz-url-standalone" name="url" placeholder="https://example.com" required>

				<label for="dz-title-standalone">Site Name <span class="dz-optional">(optional)</span></label>
				<input type="text" id="dz-title-standalone" name="title" placeholder="e.g. Cat Bounce" maxlength="100">

				<button type="submit" class="dz-submit-btn"><?php echo esc_html( $atts['btn_label'] ); ?></button>
			</form>

			<p id="dz-submit-msg-standalone" class="dz-meta" hidden></p>
		</div>

		<script>
		( function( $ ) {
			$( '#distractinator-form' ).on( 'submit', function( e ) {
				e.preventDefault();
				var url   = $( '#dz-url-standalone' ).val().trim();
				var title = $( '#dz-title-standalone' ).val().trim();
				var msg   = $( '#dz-submit-msg-standalone' );

				if ( ! url ) {
					msg.text( 'Please enter a URL.' ).css( 'color', '#e94560' ).prop( 'hidden', false );
					return;
				}

				$.ajax( {
					url:  distractinator.ajaxUrl,
					type: 'POST',
					data: { action: 'distractinator_submit', nonce: distractinator.nonce, url: url, title: title },
					success: function( r ) {
						if ( r.success ) {
							msg.text( r.data ).css( 'color', '#7bc67e' ).prop( 'hidden', false );
							$( '#distractinator-form' )[0].reset();
						} else {
							msg.text( r.data || 'Submission failed.' ).css( 'color', '#e94560' ).prop( 'hidden', false );
						}
					},
					error: function() {
						msg.text( 'Something went wrong. Please try again.' ).css( 'color', '#e94560' ).prop( 'hidden', false );
					}
				} );
			} );
		} )( jQuery );
		</script>
		<?php
		return ob_get_clean();
	}
}
