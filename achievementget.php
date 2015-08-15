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
	 * Cache posts if we're checking multiple achievements on a single page
	 */
	private $post_cache = array();

	/**
	 * The table in which we store achievement notifications.
	 */
	private $table_name;

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
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Return the single instance of our class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WP_AchievementGet();
		}

		return self::$instance;
	}

	public function init() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'achievement_get';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_shortcode( 'achievement_award', array( $this, 'achievement_award' ) );
		add_shortcode( 'achievement_profile', array( $this, 'achievement_profile' ) );
		add_shortcode( 'achievement_points', array( $this, 'achievement_points' ) );

		add_action( 'wp_ajax_hide_achievement_notify', array( $this, 'hide_achievement_notify' ) );

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
	}

	/**
	 * Create a table to store the achievement notifications.
	 */
	public function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . $this->table_name . " (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			time TIMESTAMP NOT NULL,
			seen BOOLEAN NOT NULL,
			message TEXT NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
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

	/**
	 * Show the admin page, where we can view or clear achievements.
	 * TODO: Add nonce.
	 */
	public function plugin_settings_page() {
		global $wpdb;

		if ( isset( $_GET[ 'view_user_id' ] ) ) {
			$user_id = intval( $_GET[ 'view_user_id' ] );
			$meta = get_user_meta( $user_id, self::DOMAIN . '_user_meta', true );
			print_r( $meta );

			echo( $this->achievement_points( array( 'user_id' => $user_id ) ) );
		}

		if ( isset( $_GET[ 'reset_user_id' ] ) ) {
			$user_id = intval( $_GET[ 'reset_user_id' ] );
			update_user_meta( $user_id, self::DOMAIN . '_user_meta', array() );
			update_user_meta( $user_id, self::DOMAIN . '_points', 0 );
		}

		if ( isset( $_GET[ 'admin_form' ] ) ) {
			if ( isset( $_GET[ 'admin_only' ] ) ) {
				update_option( 'achievement_get_admin_only', 1 );

			} else {
				update_option( 'achievement_get_admin_only', 0 );
			}
		}

		$admin_only = get_option( 'achievement_get_admin_only', 0 );

?>

<h1>Achievement Get!</h1>

<form method="get">
  <input type="hidden" name="page" value="<?php echo( $_GET[ 'page' ] ); ?>" />
  <b>View achievements for user ID</b>: <input type="text" name="view_user_id" value="" />
  <input name="view_form" type="submit">
</form>

<form method="get">
  <input type="hidden" name="page" value="<?php echo( $_GET[ 'page' ] ); ?>" />
  <b>Reset achievements for user ID</b>: <input type="text" name="reset_user_id" value="" />
  <input name="reset_form" type="submit">
</form>

<form method="get">
  <input type="hidden" name="page" value="<?php echo( $_GET[ 'page' ] ); ?>" />
  <b>Award achievements only to administrators</b>: <input type="checkbox" name="admin_only" <?php echo( $admin_only == 1 ? 'checked ' : '' ); ?>/>
  <input name="admin_form" type="submit">
</form>

<?php
	}

	public function achievement_award( $atts ) {
		if ( isset( $atts[ 'user_id' ] ) ) {
			$user_id = intval( $atts[ 'user_id' ] );
		} else {
			$user_id = get_current_user_id();
		}

		$user_points = intval( get_user_meta( $user_id, self::DOMAIN . '_points', true ) );

		// If we don't have a valid user id, we can't award an achievement.
		if ( 0 === $user_id ) {
			return '';
		}

		$admin_only = get_option( 'achievement_get_admin_only', 0 );

		if ( $admin_only > 0 && ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$achievement_id = intval( $atts[ 'id' ] );
		$achievement_meta = isset( $atts[ 'meta' ] ) ? intval( $atts[ 'meta' ] ) : 0;
		$achievement_points = isset( $atts[ 'points' ] ) ? intval( $atts[ 'points' ] ) : 0;
		$achievement_key = $achievement_id . ':' . $achievement_meta;

		$user_meta = get_user_meta( $user_id, self::DOMAIN . '_user_meta', true );

		// If the user already has the achievement, don't award again.
		if ( isset( $user_meta[ $achievement_key ] ) ) {
			return '';
		}

		if ( ! isset( $this->post_cache[ $achievement_id ] ) ) {
			$this->post_cache[ $achievement_id ] = get_post( $achievement_id );
		}
		$achievement_post = $this->post_cache[ $achievement_id ];

		// The achievement (CPT) hasn't been defined yet, don't award.
		if ( null === $achievement_post ) {
			return '';
		}

		$achievement_time = current_time( 'timestamp', true );
		$user_meta[ $achievement_key ] = $achievement_time;
		update_user_meta( $user_id, self::DOMAIN . '_user_meta', $user_meta );
		update_user_meta( $user_id, self::DOMAIN . '_points', $user_points + $achievement_points );

		do_action( 'achievement_award', $achievement_post, $atts );

		$award_html = apply_filters( 'achievement_award_filter', '', $achievement_post );

		return $award_html;
	}

	public function achievement_profile( $atts ) {
		global $wpdb;

		if ( isset( $atts[ 'user_id' ] ) ) {
			$user_id = intval( $atts[ 'user_id' ] );
		} else {
			$user_id = get_current_user_id();
		}

		$achievement_names = array();

		$results = $wpdb->get_results(
			'SELECT id, post_title FROM wp_posts WHERE post_type="' . self::CPT_ACHIEVEMENT . '"',
			ARRAY_A );

		foreach ( $results as $result ) {
			$achievement_names[ $result[ 'id' ] ] = $result[ 'post_title' ];
		}

		// TODO: Handle different user ids here, don't rely on local storage of metadata. Just get it!
		$st = '<div class="achievement_profile"><ul>';
		foreach ( $this->user_meta as $k => $v ) {
			$st .= '<li><span class="achievement_name"><a href="?p=' . $k . '">' . $achievement_names[ $k ] . '</a></span>: <span class="achievement_time">' . date( 'F j, Y, g:ia', $v ) . '</span></li>';
		}
		$st .= '</ul></div>';

		return $st;
	}

	public function achievement_points( $atts ) {
		$points = 0;

		if ( isset( $atts[ 'user_id' ] ) ) {

			$user_id = intval( $atts[ 'user_id' ] );
			$points = get_user_meta( $user_id, self::DOMAIN . '_points', true );

		} else if ( get_current_user_id() > 0 ) {

			$points = get_user_meta( get_current_user_id(), self::DOMAIN . '_points', true );

		}

		return $points;
	}

	public function get_notifications( $user_id ) {
		global $wpdb;

		$user_id = intval( $user_id );

		$results_obj = $wpdb->get_results(
			'SELECT * FROM ' . $this->table_name . ' WHERE user_id=' . $user_id . ' AND seen=0 ORDER BY time DESC',
			ARRAY_A
		);

		return $results_obj;
	}

	public function add_achievement_notify( $user_id, $time, $message ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'user_id' => $user_id,
				'time' => $time,
				'seen' => 0,
				'message' => $message,
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	public function hide_achievement_notify() {
		global $wpdb;

		if ( ! isset( $_GET[ 'notify_id' ] ) ) {
			wp_die();
		}

		$id = intval( $_GET[ 'notify_id' ] );
		$user_id = get_current_user_id();

		$wpdb->update(
			$this->table_name,
			array( 'seen' => 1 ),
			array(
				'id' => $id,
				'user_id' => $user_id
			)
		);

		wp_die();
	}

	public function get_achievement_points( $user_id ) {
		return intval( get_user_meta( $user_id, self::DOMAIN . '_points', true ) );
	}

	public function set_achievement_points( $user_id, $points ) {
		update_user_meta( $user_id, self::DOMAIN . '_points', $points );
	}

}

$wp_achievementget = WP_AchievementGet::get_instance();
