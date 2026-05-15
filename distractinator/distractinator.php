<?php
/**
 * Plugin Name: Distractinator
 * Plugin URI:  https://github.com/yourusername/distractinator
 * Description: A curated random redirect to useless-but-fun websites. Add the [distractinator] shortcode to any page.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: distractinator
 */

defined( 'ABSPATH' ) || exit;

define( 'DISTRACTINATOR_VERSION', '1.0.0' );
define( 'DISTRACTINATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DISTRACTINATOR_URL', plugin_dir_url( __FILE__ ) );

require_once DISTRACTINATOR_PATH . 'includes/class-cpt.php';
require_once DISTRACTINATOR_PATH . 'includes/class-admin.php';
require_once DISTRACTINATOR_PATH . 'includes/class-api.php';
require_once DISTRACTINATOR_PATH . 'includes/class-cron.php';
require_once DISTRACTINATOR_PATH . 'includes/class-shortcode.php';
require_once DISTRACTINATOR_PATH . 'includes/class-import.php';
require_once DISTRACTINATOR_PATH . 'includes/class-dashboard.php';

new Distractinator_CPT();
new Distractinator_Admin();
new Distractinator_API();
new Distractinator_Cron();
new Distractinator_Shortcode();
new Distractinator_Import();
new Distractinator_Dashboard();

register_activation_hook( __FILE__, 'distractinator_activate' );
register_deactivation_hook( __FILE__, 'distractinator_deactivate' );

function distractinator_activate() {
	Distractinator_Cron::schedule();
	distractinator_seed();
}

function distractinator_deactivate() {
	Distractinator_Cron::unschedule();
}

function distractinator_seed() {
	if ( get_option( 'distractinator_seeded' ) ) {
		return;
	}

	$sites = [
		[ 'title' => 'Find the Invisible Cow',      'url' => 'https://findtheinvisiblecow.com' ],
		[ 'title' => 'Koalas to the Max',           'url' => 'https://www.koalastothemax.com' ],
		[ 'title' => 'Pointer Pointer',              'url' => 'https://pointerpointer.com' ],
		[ 'title' => 'Staggering Beauty',            'url' => 'https://www.staggeringbeauty.com' ],
		[ 'title' => 'Zoom Quilt',                   'url' => 'https://zoomquilt.org' ],
		[ 'title' => 'Cat Bounce',                   'url' => 'https://cat-bounce.com' ],
		[ 'title' => 'Endless Horse',                'url' => 'http://endless.horse' ],
		[ 'title' => 'The Nicest Place on the Internet', 'url' => 'https://thenicestplace.net' ],
		[ 'title' => 'Is It Christmas?',             'url' => 'https://isitchristmas.com' ],
		[ 'title' => 'Hacker Typer',                 'url' => 'https://hackertyper.net' ],
		[ 'title' => 'Falling Falling',              'url' => 'https://www.fallingfalling.com' ],
		[ 'title' => 'Paper Toilet',                 'url' => 'https://papertoilet.com' ],
		[ 'title' => 'Eel Slap',                     'url' => 'https://www.eelslap.com' ],
		[ 'title' => 'Patience is a Virtue',         'url' => 'https://patience.is' ],
		[ 'title' => 'Make Everything OK',           'url' => 'https://make-everything-ok.com' ],
		[ 'title' => 'Nyan Cat',                     'url' => 'https://www.nyan.cat' ],
		[ 'title' => 'Doge Weather',                 'url' => 'https://dogeweather.com' ],
		[ 'title' => 'Bees Bees Bees',               'url' => 'https://beesbeesbees.com' ],
		[ 'title' => 'Corndog Clicker',              'url' => 'https://corndogclicker.com' ],
		[ 'title' => 'Worlds Biggest Pac-Man',       'url' => 'https://worldsbiggestpacman.com' ],
		[ 'title' => 'Procatinator',                 'url' => 'https://procatinator.com' ],
		[ 'title' => 'Passive Aggressive Passwords', 'url' => 'https://www.passiveaggressivepasswords.com' ],
		[ 'title' => 'Pixels Fighting',             'url' => 'https://www.pixelsfighting.com' ],
		[ 'title' => 'Incredibox',                   'url' => 'https://www.incredibox.com' ],
		[ 'title' => 'Patatap',                      'url' => 'https://patatap.com' ],
		[ 'title' => 'Draw a Stickman',              'url' => 'https://www.drawastickman.com' ],
		[ 'title' => 'A Soft Murmur',                'url' => 'https://asoftmurmur.com' ],
		[ 'title' => 'Rainy Mood',                   'url' => 'https://rainymood.com' ],
		[ 'title' => 'Calm',                         'url' => 'https://www.calm.com' ],
		[ 'title' => 'Silk',                         'url' => 'https://weavesilk.com' ],
		[ 'title' => 'Fluid Simulation',             'url' => 'https://paveldogreat.github.io/WebGL-Fluid-Simulation/' ],
		[ 'title' => 'Neal Fun: Life Stats',         'url' => 'https://neal.fun/life-stats/' ],
		[ 'title' => 'Neal Fun: The Deep Sea',       'url' => 'https://neal.fun/deep-sea/' ],
		[ 'title' => 'Pointer Fun',                  'url' => 'https://pointerfun.com' ],
		[ 'title' => 'Geo Guessr',                   'url' => 'https://www.geoguessr.com' ],
		[ 'title' => 'Radio Garden',                 'url' => 'https://radio.garden' ],
		[ 'title' => 'A Window in Brussels',         'url' => 'https://www.windows93.net' ],
		[ 'title' => 'Spin the Wheel',               'url' => 'https://pickerwheel.com' ],
		[ 'title' => 'Cursors.io',                   'url' => 'https://cursors.io' ],
		[ 'title' => 'Quick Draw',                   'url' => 'https://quickdraw.withgoogle.com' ],
		[ 'title' => 'Interland',                    'url' => 'https://beinternetawesome.withgoogle.com/en_us/interland' ],
		[ 'title' => 'Chrome Dino',                  'url' => 'https://chromedino.com' ],
		[ 'title' => 'Crossy Road',                  'url' => 'https://crossyroad.com' ],
		[ 'title' => 'Line Rider',                   'url' => 'https://www.linerider.com' ],
		[ 'title' => 'Helicopter Game',              'url' => 'https://www.helicoptergame.net' ],
		[ 'title' => 'Cookie Clicker',               'url' => 'https://orteil.dashnet.org/cookieclicker/' ],
		[ 'title' => 'Addicting Games',              'url' => 'https://www.addictinggames.com' ],
		[ 'title' => 'Akinator',                     'url' => 'https://en.akinator.com' ],
		[ 'title' => 'TyprX',                        'url' => 'https://typrx.com' ],
		[ 'title' => 'Pixelator',                    'url' => 'https://www.pixelatorapp.com' ],
	];

	foreach ( $sites as $site ) {
		wp_insert_post( [
			'post_title'   => sanitize_text_field( $site['title'] ),
			'post_status'  => 'publish',
			'post_type'    => 'distractinator_site',
			'meta_input'   => [
				'_distractinator_url'    => esc_url_raw( $site['url'] ),
				'_distractinator_clicks' => 0,
				'_distractinator_active' => 1,
			],
		] );
	}

	update_option( 'distractinator_seeded', true );
}
