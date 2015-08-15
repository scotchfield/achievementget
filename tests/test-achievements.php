<?php

class Test_AchievementGet extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

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

}
