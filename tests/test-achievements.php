<?php

class Test_AchievementGet extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$this->class = WP_AchievementGet::get_instance();
	}

	public function tearDown() {
		unset( $this->class );

		parent::tearDown();
	}

	/**
	 * @covers WP_AchievementGet::get_instance
	 */
	public function test_get_instance() {
		$class = WP_AchievementGet::get_instance();

		$this->assertNotNull( $class );
	}

	/**
	 * @covers WP_AchievementGet::get_instance
	 */
	public function test_get_instance_same() {
		$this->assertEquals( WP_AchievementGet::get_instance(), WP_AchievementGet::get_instance() );
	}

	/**
	 * @covers WP_AchievementGet::init
	 */
	public function test_init() {
		$this->class->init();

		$this->assertTrue( shortcode_exists( 'achievement_award' ) );
		$this->assertTrue( shortcode_exists( 'achievement_profile' ) );
		$this->assertTrue( shortcode_exists( 'achievement_points' ) );

		$this->assertTrue( post_type_exists( WP_AchievementGet::CPT_ACHIEVEMENT ) );
	}

	/**
	 * @covers WP_AchievementGet::install
	 */
	public function test_install() {
		global $wpdb;

		$this->class->install();

		$this->assertEquals(
			$this->class->table_name,
			$wpdb->get_var( 'SHOW TABLES LIKE "' . $this->class->table_name . '"')
		);
	}

	/**
	 * @covers WP_AchievementGet::add_admin_menu
	 */
	public function test_add_admin_menu() {
		$this->assertNotFalse( $this->class->add_admin_menu() );
	}




	/**
	 * @covers WP_AchievementGet::get_achievement_points
	 */
	public function test_get_achievement_points_empty() {
		$this->assertEquals( 0, $this->class->get_achievement_points( get_current_user_id() ) );
	}

	/**
	 * @covers WP_AchievementGet::get_achievement_points
	 */
	public function test_get_achievement_points_new_user() {
		$user = new WP_User( $this->factory->user->create() );
		$old_user_id = get_current_user_id();
		wp_set_current_user( $user->ID );

		$this->assertEquals( 0, $this->class->get_achievement_points( get_current_user_id() ) );

		wp_set_current_user( $old_user_id );
	}

	/**
	 * @covers WP_AchievementGet::get_achievement_points
	 * @covers WP_AchievementGet::set_achievement_points
	 */
	public function test_set_and_get_achievement_points_new() {
		$user = new WP_User( $this->factory->user->create() );
		$old_user_id = get_current_user_id();
		wp_set_current_user( $user->ID );

		$points = 123;

		$this->class->set_achievement_points( get_current_user_id(), $points );

		$this->assertEquals( $points, $this->class->get_achievement_points( get_current_user_id() ) );

		wp_set_current_user( $old_user_id );
	}

}
