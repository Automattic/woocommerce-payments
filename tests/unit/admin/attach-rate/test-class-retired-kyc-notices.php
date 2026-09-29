<?php
/**
 * Tests for retired activation notices.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Extensions can still depend on the retired notices' asset handles.
 */
class Retired_Kyc_Notices_Test extends WCPAY_UnitTestCase {

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
