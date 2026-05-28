<?php
/**
 * Class WSN_Settings_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WSN_Settings unit tests.
 *
 * Focuses on the locked default-value rule for is_enabled() (unset must read as false,
 * never as a synthetic default '1'), plus validation/sanitization on every setter.
 */
class WSN_Settings_Test extends WCPAY_UnitTestCase {

	public function tear_down() {
		delete_option( WSN_Settings::OPTION_ENABLED );
		delete_option( WSN_Settings::OPTION_VISIBILITY_MODE );
		delete_option( WSN_Settings::OPTION_VISIBILITY_TERMS );
		delete_option( WSN_Settings::OPTION_VISIBILITY_PRODUCT_IDS );
		delete_option( WSN_Settings::OPTION_HERO_IMAGE_ID );
		delete_option( WSN_Settings::OPTION_LOGO_OVERRIDE_ID );
		delete_option( WSN_Settings::OPTION_CONTACT_EMAIL );
		delete_option( WSN_Settings::OPTION_REFUND_PAGE_ID );
		parent::tear_down();
	}

	/**
	 * The critical default rule: unset MUST read as false, never as a synthetic default '1'.
	 *
	 * This is the contract the WSN indexer (RSM-3946) depends on for its fail-closed
	 * `WHERE wcpay_wsn_enabled = '1'` filter. If this test breaks, every WCPay merchant
	 * on the planet lands in WSN search results by default.
	 */
	public function test_is_enabled_returns_false_when_option_is_unset() {
		$this->assertFalse( WSN_Settings::is_enabled() );
	}

	public function test_is_enabled_returns_false_when_option_is_zero_string() {
		update_option( WSN_Settings::OPTION_ENABLED, '0' );
		$this->assertFalse( WSN_Settings::is_enabled() );
	}

	public function test_is_enabled_returns_true_only_for_explicit_one_string() {
		update_option( WSN_Settings::OPTION_ENABLED, '1' );
		$this->assertTrue( WSN_Settings::is_enabled() );
	}

	public function test_set_enabled_writes_one_or_zero_not_delete() {
		WSN_Settings::set_enabled( true );
		$this->assertSame( '1', get_option( WSN_Settings::OPTION_ENABLED ) );

		// Explicit opt-out persists '0' (not delete) so we can distinguish from never-engaged state.
		WSN_Settings::set_enabled( false );
		$this->assertSame( '0', get_option( WSN_Settings::OPTION_ENABLED ) );
	}

	public function test_set_enabled_writes_with_autoload_false() {
		WSN_Settings::set_enabled( true );

		global $wpdb;
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				WSN_Settings::OPTION_ENABLED
			)
		);
		// 'no' is the WP-internal representation for autoload=false.
		$this->assertSame( 'no', $autoload );
	}

	public function test_get_visibility_mode_defaults_to_all_when_unset() {
		$this->assertSame( WSN_Settings::VISIBILITY_MODE_ALL, WSN_Settings::get_visibility_mode() );
	}

	public function test_get_visibility_mode_falls_back_to_all_for_invalid_stored_value() {
		// Simulate a corrupt option (e.g., manual DB edit).
		update_option( WSN_Settings::OPTION_VISIBILITY_MODE, 'rubbish' );
		$this->assertSame( WSN_Settings::VISIBILITY_MODE_ALL, WSN_Settings::get_visibility_mode() );
	}

	public function test_set_visibility_mode_rejects_invalid_input() {
		$this->assertFalse( WSN_Settings::set_visibility_mode( 'invalid' ) );
		$this->assertFalse( get_option( WSN_Settings::OPTION_VISIBILITY_MODE, false ) );
	}

	public function test_set_visibility_mode_accepts_each_valid_mode() {
		foreach ( WSN_Settings::valid_visibility_modes() as $mode ) {
			$this->assertTrue( WSN_Settings::set_visibility_mode( $mode ) );
			$this->assertSame( $mode, WSN_Settings::get_visibility_mode() );
		}
	}

	public function test_get_visibility_terms_normalizes_partial_storage() {
		// Partial: legacy structure missing the 'brands' key.
		update_option(
			WSN_Settings::OPTION_VISIBILITY_TERMS,
			[
				'categories' => [ 14, 22 ],
				'tags'       => [ 7 ],
			]
		);
		$terms = WSN_Settings::get_visibility_terms();
		$this->assertSame( [ 14, 22 ], $terms['categories'] );
		$this->assertSame( [ 7 ], $terms['tags'] );
		$this->assertSame( [], $terms['brands'] );
	}

	public function test_set_visibility_terms_sanitizes_non_numeric_and_negative_ids() {
		WSN_Settings::set_visibility_terms(
			[
				'categories' => [ 14, '22', 'rubbish', -5, 0, 22 /* duplicate */ ],
			]
		);
		$this->assertSame( [ 14, 22 ], WSN_Settings::get_visibility_terms()['categories'] );
	}

	public function test_set_visibility_product_ids_rejects_when_exceeds_mvp_cap() {
		$too_many = range( 1, WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS + 1 );
		$this->assertFalse( WSN_Settings::set_visibility_product_ids( $too_many ) );
		$this->assertSame( [], WSN_Settings::get_visibility_product_ids() );
	}

	public function test_set_visibility_product_ids_accepts_at_cap() {
		$at_cap = range( 1, WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS );
		$this->assertTrue( WSN_Settings::set_visibility_product_ids( $at_cap ) );
		$this->assertCount( WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS, WSN_Settings::get_visibility_product_ids() );
	}

	public function test_set_contact_email_sanitizes_and_persists_valid() {
		$this->assertTrue( WSN_Settings::set_contact_email( 'hello@example.com' ) );
		$this->assertSame( 'hello@example.com', WSN_Settings::get_contact_email() );
	}

	public function test_set_contact_email_rejects_invalid_non_empty_string() {
		$this->assertFalse( WSN_Settings::set_contact_email( 'not-an-email' ) );
		$this->assertNull( WSN_Settings::get_contact_email() );
	}

	public function test_set_contact_email_with_null_or_empty_clears_option() {
		WSN_Settings::set_contact_email( 'hello@example.com' );
		$this->assertTrue( WSN_Settings::set_contact_email( null ) );
		$this->assertNull( WSN_Settings::get_contact_email() );
	}

	public function test_set_refund_page_id_rejects_when_page_not_published() {
		$draft_page_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'draft',
			]
		);
		$this->assertFalse( WSN_Settings::set_refund_page_id( $draft_page_id ) );
		$this->assertNull( WSN_Settings::get_refund_page_id() );
	}

	public function test_set_refund_page_id_rejects_post_that_is_not_a_page() {
		$post_id = $this->factory->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$this->assertFalse( WSN_Settings::set_refund_page_id( $post_id ) );
	}

	public function test_set_refund_page_id_accepts_published_page() {
		$page_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$this->assertTrue( WSN_Settings::set_refund_page_id( $page_id ) );
		$this->assertSame( $page_id, WSN_Settings::get_refund_page_id() );
	}

	public function test_get_refund_page_id_returns_null_when_underlying_page_unpublished_after_save() {
		$page_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		WSN_Settings::set_refund_page_id( $page_id );

		// Page gets unpublished out-of-band.
		wp_update_post(
			[
				'ID'          => $page_id,
				'post_status' => 'draft',
			]
		);

		// Reader returns null even though the option still holds the ID — the Profile
		// tab uses this to clear stale picker state.
		$this->assertNull( WSN_Settings::get_refund_page_id() );
	}

	public function test_set_hero_image_id_rejects_post_that_is_not_an_attachment() {
		$page_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$this->assertFalse( WSN_Settings::set_hero_image_id( $page_id ) );
		$this->assertNull( WSN_Settings::get_hero_image_id() );
	}

	public function test_set_hero_image_id_rejects_non_image_attachment() {
		// Attachments that aren't images (e.g., PDF) must also be rejected.
		$pdf_attachment = $this->factory->attachment->create_object(
			[
				'file'           => 'fake.pdf',
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
			]
		);
		$this->assertFalse( WSN_Settings::set_hero_image_id( $pdf_attachment ) );
		$this->assertNull( WSN_Settings::get_hero_image_id() );
	}

	public function test_set_hero_image_id_accepts_image_attachment() {
		$image_attachment = $this->factory->attachment->create_object(
			[
				'file'           => 'fake.png',
				'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
			]
		);
		$this->assertTrue( WSN_Settings::set_hero_image_id( $image_attachment ) );
		$this->assertSame( $image_attachment, WSN_Settings::get_hero_image_id() );
	}

	public function test_set_hero_image_id_with_null_clears_option() {
		$image_attachment = $this->factory->attachment->create_object(
			[
				'file'           => 'fake.png',
				'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
			]
		);
		WSN_Settings::set_hero_image_id( $image_attachment );
		$this->assertTrue( WSN_Settings::set_hero_image_id( null ) );
		$this->assertNull( WSN_Settings::get_hero_image_id() );
	}

	public function test_set_logo_override_id_rejects_non_image_attachment() {
		// set_logo_override_id shares its validation with set_hero_image_id — one
		// representative test covers the contract; per-shape coverage of attachment
		// vs page vs PDF is exercised on set_hero_image_id above.
		$pdf_attachment = $this->factory->attachment->create_object(
			[
				'file'           => 'fake.pdf',
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
			]
		);
		$this->assertFalse( WSN_Settings::set_logo_override_id( $pdf_attachment ) );
		$this->assertNull( WSN_Settings::get_logo_override_id() );
	}

	public function test_get_all_returns_expected_shape_when_everything_is_unset() {
		$all = WSN_Settings::get_all();
		$this->assertSame(
			[
				'enabled',
				'visibility_mode',
				'visibility_terms',
				'visibility_product_ids',
				'hero_image_id',
				'logo_override_id',
				'contact_email',
				'refund_page_id',
			],
			array_keys( $all )
		);
		$this->assertFalse( $all['enabled'] );
		$this->assertSame( WSN_Settings::VISIBILITY_MODE_ALL, $all['visibility_mode'] );
		$this->assertSame(
			[
				'categories' => [],
				'tags'       => [],
				'brands'     => [],
			],
			$all['visibility_terms']
		);
		$this->assertSame( [], $all['visibility_product_ids'] );
		$this->assertNull( $all['hero_image_id'] );
		$this->assertNull( $all['logo_override_id'] );
		$this->assertNull( $all['contact_email'] );
		$this->assertNull( $all['refund_page_id'] );
	}
}
