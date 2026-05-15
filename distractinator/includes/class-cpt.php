<?php
defined( 'ABSPATH' ) || exit;

class Distractinator_CPT {

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register() {
		register_post_type( 'distractinator_site', [
			'label'               => 'Distractinator Sites',
			'labels'              => [
				'name'               => 'Distractinator Sites',
				'singular_name'      => 'Distractinator Site',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Distractinator Site',
				'edit_item'          => 'Edit Distractinator Site',
				'new_item'           => 'New Distractinator Site',
				'view_item'          => 'View Distractinator Site',
				'search_items'       => 'Search Distractinator Sites',
				'not_found'          => 'No distractinator sites found',
				'not_found_in_trash' => 'No distractinator sites found in Trash',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // We add our own menu
			'capability_type'     => 'post',
			'supports'            => [ 'title' ],
			'menu_icon'           => 'dashicons-randomize',
		] );
	}
}
