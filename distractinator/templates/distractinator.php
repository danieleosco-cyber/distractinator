<?php defined( 'ABSPATH' ) || exit; ?>

<div id="distractinator-wrap" style="--dz-bg:<?php echo esc_attr( $bg_color ); ?>;--dz-btn:<?php echo esc_attr( $btn_color ); ?>;">

	<div id="distractinator-stage">
		<div id="distractinator-content">
			<h1 id="distractinator-heading"><?php echo esc_html( $atts['heading'] ); ?></h1>
			<p id="distractinator-subheading"><?php echo esc_html( $atts['subheading'] ); ?></p>

			<button id="distractinator-btn" type="button">
				<span id="distractinator-btn-text"><?php echo esc_html( $atts['button'] ); ?></span>
				<span id="distractinator-spinner" class="dz-spinner" hidden></span>
			</button>

			<p id="distractinator-counter" class="dz-meta"></p>
		</div>
	</div>

	<div id="distractinator-report-wrap" hidden>
		<button id="distractinator-report-btn" class="dz-link-btn" type="button">Report last link as dead &times;</button>
		<span id="dz-report-msg" class="dz-meta" hidden></span>
	</div>

	<?php if ( $allow_sub ) : ?>
	<div id="distractinator-submit-wrap">
		<button id="distractinator-submit-toggle" class="dz-link-btn" type="button">Know a distractinator site? Submit it &darr;</button>

		<div id="distractinator-submit-form" hidden>
			<form id="distractinator-form" novalidate>
				<input type="url" id="dz-url" name="url" placeholder="https://example.com" required>
				<input type="text" id="dz-title" name="title" placeholder="Site name (optional)" maxlength="100">
				<button type="submit" class="dz-submit-btn">Submit</button>
			</form>
			<p id="dz-submit-msg" class="dz-meta" hidden></p>
		</div>
	</div>
	<?php endif; ?>

</div>
