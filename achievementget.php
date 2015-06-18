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
		global $wpdb;

		if ( isset( self::$instance ) ) {
			wp_die( esc_html__( 'The WP_AchievementGet class has already been instantiated.', self::DOMAIN ) );
		}

		self::$instance = $this;

		$this->table_name = $wpdb->prefix . 'achievement_get';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_shortcode( 'achievement_award', array( $this, 'achievement_award' ) );
		add_shortcode( 'achievement_profile', array( $this, 'achievement_profile' ) );

		add_action( 'wp_ajax_hide_achievement_notify', array( $this, 'hide_achievement_notify' ) );
	}

	public static function get_instance() {
		return self::$instance;
	}

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
		$this->user_points = get_user_meta( $this->user_id, self::DOMAIN . '_points', true );
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
		global $wpdb;

		if ( isset( $_GET[ 'view_user_id' ] ) ) {
			$user_id = intval( $_GET[ 'view_user_id' ] );
			$meta = get_user_meta( $user_id, self::DOMAIN . '_user_meta', true );
			print_r( $meta );
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
		$user_id = intval( $this->user_id );
		$user_points = intval( $this->user_points );

		if ( isset( $atts[ 'user_id' ] ) ) {
			$user_id = intval( $atts[ 'user_id' ] );
		}

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

		$achievement_post = get_post( $achievement_id );

		// The achievement (CPT) hasn't been defined yet, don't award.
		if ( null === $achievement_post ) {
			return '';
		}

		$achievement_time = current_time( 'timestamp', true );
		$user_meta[ $achievement_key ] = $achievement_time;
		update_user_meta( $user_id, self::DOMAIN . '_user_meta', $user_meta );
		update_user_meta( $user_id, self::DOMAIN . '_points', $user_points + $achievement_points );

		if ( $this->user_id === $user_id ) {
			$this->user_meta = $user_meta;
			$this->user_points = $user_points;
		}

		do_action( 'achievement_award', $achievement_post );

		$award_html = apply_filters( 'achievement_award_filter', '', $achievement_post );

		return $award_html;
	}

	public function achievement_profile( $atts ) {
		global $wpdb;

		// If the user is not logged in, we can't show their list of achievements.
		if ( 0 === $this->user_id ) {
			return '';
		}

		$achievement_names = array();

		$results = $wpdb->get_results(
			'SELECT id, post_title FROM wp_posts WHERE post_type="' . self::CPT_ACHIEVEMENT . '"',
			ARRAY_A );

		foreach ( $results as $result ) {
			$achievement_names[ $result[ 'id' ] ] = $result[ 'post_title' ];
		}

		$st = '<div class="achievement_profile"><ul>';
		foreach ( $this->user_meta as $k => $v ) {
			$st .= '<li><span class="achievement_name"><a href="?p=' . $k . '">' . $achievement_names[ $k ] . '</a></span>: <span class="achievement_time">' . date( 'F j, Y, g:ia', $v ) . '</span></li>';
		}
		$st .= '</ul></div>';

		return $st;
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

}

$wp_achievementget = new WP_AchievementGet();
