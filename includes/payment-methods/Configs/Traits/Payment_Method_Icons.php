<?php
/**
 * Payment Method Icons Trait
 *
 * @package WCPay\PaymentMethods\Configs\Traits
 */

namespace WCPay\PaymentMethods\Configs\Traits;

/**
 * Trait for handling payment method icon paths and URLs.
 */
trait Payment_Method_Icons {
	/**
	 * Get the base path for payment method icons.
	 *
	 * @return string
	 */
	private function get_icon_base_path(): string {
		return '/assets/images/payment-methods';
	}

	/**
	 * Get the base filename for the payment method's icons.
	 * Can be overridden by payment methods to provide custom icon filenames.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	protected function get_icon_filename_base( ?string $account_country = null ): string {
		return $this->get_id();
	}

	/**
	 * Get the relative paths for the payment method icons.
	 *
	 * @return array{
	 *   default: array{path: string},
	 *   dark?: array{path: string}
	 * }
	 */
	public function get_relative_icon_paths(): array {
		$base_path      = $this->get_icon_base_path();
		$filename_base  = $this->get_icon_filename_base();
		$dark_icon_path = "{$base_path}/{$filename_base}-dark.svg";

		$icons = [
			'default' => [
				'path' => "{$base_path}/{$filename_base}.svg",
			],
		];

		// Get the plugin root directory.
		$plugin_root = dirname( dirname( dirname( dirname( __DIR__ ) ) ) );

		// Only include dark icon if it exists.
		if ( file_exists( $plugin_root . $dark_icon_path ) ) {
			$icons['dark'] = [
				'path' => $dark_icon_path,
			];
		}

		return $icons;
	}

	/**
	 * Get the icons for the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return array<string,array{path:string}>
	 */
	public function get_icons( ?string $account_country = null ): array {
		$base        = $this->get_icon_filename_base( $account_country );
		$plugin_root = dirname( dirname( dirname( dirname( __DIR__ ) ) ) );

		$icons = [
			'default' => [
				'path' => plugin_dir_url( WCPAY_PLUGIN_FILE ) . '/assets/images/payment-methods/' . $base . '.svg',
			],
		];

		$dark_icon_filesystem_path = $plugin_root . '/assets/images/payment-methods/' . $base . '-dark.svg';
		if ( file_exists( $dark_icon_filesystem_path ) ) {
			$icons['dark'] = [
				'path' => plugin_dir_url( WCPAY_PLUGIN_FILE ) . '/assets/images/payment-methods/' . $base . '-dark.svg',
			];
		}

		return $icons;
	}
}
