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

}
