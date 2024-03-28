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

	const CACHE_KEY_PREFIX_PURPOSE = 'file_purpoose_';
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
}
