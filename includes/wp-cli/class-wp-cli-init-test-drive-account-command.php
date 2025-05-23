<?php
/**
 * Init test drive account command.
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Country_Code;

/**
 * Class WP_CLI_Init_Test_Drive_Account_Command
 */
class WP_CLI_Init_Test_Drive_Account_Command {
	/**
	 * Onboarding service.
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	protected $onboarding_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Onboarding_Service $onboarding_service Onboarding service.
	 */
	public function __construct( WC_Payments_Onboarding_Service $onboarding_service ) {
		$this->onboarding_service = $onboarding_service;
	}

	/**
	 * Initialize the test drive account.
	 *
	 * ## OPTIONS
	 *
	 * [--country=<country_code>]
	 * : The country code for the test drive account.
	 * ---
	 * default: US
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp woopayments init-test-drive-account
	 *     wp woopayments init-test-drive-account --country=GB
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Command line arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$country = isset( $assoc_args['country'] ) ? $assoc_args['country'] : 'US';
		try {
			Country_Code::search( $country );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( 'Invalid country code. Please provide a valid country code.' );
		}

		try {
			$result = $this->onboarding_service->init_test_drive_account( $country );
			if ( true === $result ) {
				WP_CLI::success( 'Test drive account initialized successfully.' );
			} else {
				WP_CLI::error( 'Failed to initialize test drive account.' );
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Failed to initialize test drive account: ' . $e->getMessage() );
		}
	}
}
