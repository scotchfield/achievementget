<?php
/**
 * Plugin Name: Achievement Get!
 * Plugin URI: http://scotchfield.com/
 * Description: Award achievements to your WordPress readers
 * Version: 1.0
 * Author: Scott Grant
 * Author URI: http://scotchfield.com/
 * License: GPL2
 */
class WP_AchievementGet {

	/**
	 * Store reference to singleton object.
	 */
	private static $instance = null;

	/**
	 * The domain for localization.
	 */
	const DOMAIN = 'achievementget';

	/**
	 * The custom post type string for achievements.
	 */
	const CPT_ACHIEVEMENT = 'achievementget-template';

	/**
	 * Instantiate, if necessary, and add hooks.
	 */
	public function __construct() {
		if ( isset( self::$instance ) ) {
			wp_die( esc_html__( 'The WP_AchievementGet class has already been instantiated.', self::DOMAIN ) );
		}

		self::$instance = $this;

		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_shortcode( 'achievement_award', array( $this, 'achievement_award' ) );
		add_shortcode( 'achievement_profile', array( $this, 'achievement_profile' ) );
	}

	/**
	 * Initialize custom types.
	 */
	public function init() {
		register_post_type(
			self::CPT_ACHIEVEMENT,
			array(
				'labels' => array(
					'name'               => esc_html__( 'Achievements',                   self::DOMAIN ),
					'singular_name'      => esc_html__( 'Achievement',                    self::DOMAIN ),
					'add_new'            => esc_html__( 'Add New Achievement',            self::DOMAIN ),
					'add_new_item'       => esc_html__( 'Add New Achievement',            self::DOMAIN ),
					'edit_item'          => esc_html__( 'Edit Achievement',               self::DOMAIN ),
					'new_item'           => esc_html__( 'New Achievement',                self::DOMAIN ),
					'view_item'          => esc_html__( 'View Achievement',               self::DOMAIN ),
					'search_items'       => esc_html__( 'Search Achievements',            self::DOMAIN ),
					'not_found'          => esc_html__( 'No achievements found',          self::DOMAIN ),
					'not_found_in_trash' => esc_html__( 'No achievements found in trash', self::DOMAIN ),
				),
				'public' => true,
				'exclude_from_search' => false,
				'show_ui' => true,
				'rewrite' => true,
			)
		);

		$this->user_id = get_current_user_id();
		$this->user_meta = get_user_meta( $user_id, self::DOMAIN . '_user_meta' );
	}

	/**
	 * Add menu options to the dashboard, and meta boxes to the edit pages.
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'Achievement Get!', self::DOMAIN ),
			esc_html__( 'Achievement Get!', self::DOMAIN ),
			'edit_posts',
			self::DOMAIN . '_menu',
			array( $this, 'generate_admin_page' )
		);
	}

	public function generate_admin_page() {

	}

	public function achievement_award( $atts ) {
		// if user is not logged in, kick out
		// if user has achievement, kick out
		// award achievement by setting user_meta, and display html

		return '';
	}

	public function achievement_profile( $atts ) {
		// show list of achievements obtained

		return '';
	}

}

$wp_achievementget = new WP_AchievementGet();
