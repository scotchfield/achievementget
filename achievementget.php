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
	const CPT_ACHIEVEMENT = 'achievement-template';

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
		$this->user_meta = get_user_meta( $this->user_id, self::DOMAIN . '_user_meta', true );
	}

	/**
	 * Add menu options to the dashboard, and meta boxes to the edit pages.
	 */
	public function add_admin_menu() {
		$page = add_options_page(
			esc_html__( 'Achievement Get!', self::DOMAIN ),
			esc_html__( 'Achievement Get!', self::DOMAIN ),
			'manage_options',
			self::DOMAIN,
			array( $this, 'plugin_settings_page' )
		);
	}

	public function plugin_settings_page() {
		$achievement_posts = new WP_Query( 'post_type=' . self::CPT_ACHIEVEMENT );
?>
<h1>Achievement Get!</h1>
<h2>List of achievements</h2>
<?php

		if ( $achievement_posts->have_posts() ) {
			echo '<ul>';
			while ( $achievement_posts->have_posts() ) {
				$achievement_posts->the_post();
				echo '<li>' . get_the_title() . '</li>';
			}
			echo '</ul>';
		} else {
			echo( '<p>No achievements found! <a href="edit.php?post_type=' . self::CPT_ACHIEVEMENT . '">Care to add one?</a></p>' );
		}

		wp_reset_postdata();

?>
<h2>Most recent achievements awarded</h2>
<?php
	}

	public function achievement_award( $atts ) {
		// If the user is not logged in, we can't award an achievement.
		if ( 0 === $this->user_id ) {
			return '';
		}

		$achievement_id = $atts[ 'id' ];

		// If the user already has the achievement, don't award again.
		if ( isset( $this->user_meta[ $achievement_id ] ) ) {
			return '';
		}

		$achievement_post = get_post( $achievement_id );

		// The achievement (CPT) hasn't been defined yet, don't award.
		if ( null === $achievement_post ) {
			return '';
		}

		$achievement_time = current_time( 'timestamp', true );
		$this->user_meta[ $achievement_id ] = $achievement_time;
		update_user_meta( $this->user_id, self::DOMAIN . '_user_meta', $this->user_meta );

		return '<div class="achievement_award"><h1>Achievement Awarded!</h1><h2>' . $achievement_post->post_title . '</h2></div>';
	}

	public function achievement_profile( $atts ) {
		// show list of achievements obtained

		return '';
	}

}

$wp_achievementget = new WP_AchievementGet();
