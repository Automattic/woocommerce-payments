<?php
/**
 * WC_Payments_File_Service class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class which handles files.
 */
class WC_Payments_File_Service {


	const FILE_PURPOSE_PUBLIC = [
		'business_logo',
		'business_icon',
	];

	const CACHE_KEY_PREFIX_PURPOSE = 'file_purpose_';
	const CACHE_PERIOD             = 86400;  // 24 h


	/**
	 * Check if a file purpose is public or needs permissions.
	 *
	 * @param string $purpose - file purpose.
	 *
	 * @return bool
	 */
	public function is_file_public( string $purpose ): bool {
		return in_array( $purpose, static::FILE_PURPOSE_PUBLIC, true );
	}

	/**
	 * Check if a file ID is the saved WooPay store logo, the only file logged-out shoppers may load.
	 *
	 * @param string $file_id - file ID.
	 *
	 * @return bool
	 */
	public function is_saved_store_logo( string $file_id ): bool {
		return '' !== $file_id && WC_Payments::get_gateway()->get_option( 'platform_checkout_store_logo' ) === $file_id;
	}
}
