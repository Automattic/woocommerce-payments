<?php
/**
 * Tests for retired activation notices.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Legacy classes cannot reinstall the retired experiment.
 */
class Retired_Kyc_Notices_Test extends WCPAY_UnitTestCase {

	/** @dataProvider retired_notices_provider */
	public function test_legacy_init_does_not_register_handlers( string $class_name ): void {
		$notice = new $class_name(
			$this->createMock( WC_Payment_Gateway_WCPay::class ),
			$this->createMock( WC_Payments_Account::class )
		);
		$notice->init_hooks();
		$notice->init_global_hooks();

		$this->assertFalse( has_action( 'admin_init', [ $notice, 'handle_cta' ] ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', [ $notice, 'enqueue_script' ] ) );
		$this->assertFalse( $notice->should_show() );
	}

	public function retired_notices_provider(): array {
		return [
			[ 'WC_Payments_Test_To_Live_Notice' ],
			[ 'WC_Payments_Post_Kyc_Activation_Notice' ],
		];
	}

	/** @dataProvider retired_notices_provider */
	public function test_stale_eligibility_does_not_render_or_enqueue_retired_notices( string $class_name ): void {
		$notice = new $class_name(
			$this->createMock( WC_Payment_Gateway_WCPay::class ),
			$this->createMock( WC_Payments_Account::class )
		);
		set_transient( $class_name::TRANSIENT_ELIGIBLE, 'yes', HOUR_IN_SECONDS );
		set_current_screen( 'woocommerce_page_wc-settings' );
		try {
			ob_start();
			$notice->maybe_show();
			$notice->enqueue_script();
			$output = ob_get_clean();

			$this->assertSame( '', $output );
			$this->assertFalse( $notice->should_show() );
			foreach ( [ 'WCPAY_TEST_TO_LIVE_NOTICE', 'WCPAY_POST_KYC_ACTIVATION_NOTICE' ] as $handle ) {
				$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
				$this->assertFalse( wp_style_is( $handle, 'enqueued' ) );
			}
		} finally {
			delete_transient( $class_name::TRANSIENT_ELIGIBLE );
			set_current_screen( 'front' );
		}
	}

	/** @dataProvider retired_asset_handles_provider */
	public function test_extensions_can_depend_on_retired_asset_handles( string $handle ): void {
		$previous_scripts = wp_scripts();
		$previous_styles  = wp_styles();
		try {
			$GLOBALS['wp_scripts'] = new WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate dependency resolution.
			$GLOBALS['wp_styles']  = new WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate dependency resolution.
			WC_Payments_Admin_Notices::register_retired_notice_assets();
			wp_register_script( 'kyc-test-extension', 'https://example.test/extension.js', [ $handle ], '1.0', true );
			wp_register_style( 'kyc-test-extension', 'https://example.test/extension.css', [ $handle ], '1.0' );

			ob_start();
			wp_scripts()->do_items( [ 'kyc-test-extension' ], 1 );
			wp_styles()->do_items( [ 'kyc-test-extension' ] );
			$output = ob_get_clean();

			$this->assertStringContainsString( 'https://example.test/extension.js', $output );
			$this->assertStringContainsString( 'https://example.test/extension.css', $output );
			$this->assertFalse( wp_scripts()->registered[ $handle ]->src );
			$this->assertFalse( wp_styles()->registered[ $handle ]->src );
		} finally {
			$GLOBALS['wp_scripts'] = $previous_scripts; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original registry.
			$GLOBALS['wp_styles']  = $previous_styles; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original registry.
		}
	}

	public function retired_asset_handles_provider(): array {
		return [
			[ 'WCPAY_TEST_TO_LIVE_NOTICE' ],
			[ 'WCPAY_POST_KYC_ACTIVATION_NOTICE' ],
		];
	}
}
