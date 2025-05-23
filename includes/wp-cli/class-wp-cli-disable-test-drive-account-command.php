<?php
/**
 * Init test drive account command.
 *
 * @package WooCommerce\Payments
 */

/**
 * Class WP_CLI_Disable_Test_Drive_Account_Command
 */
class WP_CLI_Disable_Test_Drive_Account_Command {
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
	 * Disable the test drive account.
	 *
	 * ## EXAMPLES
	 *
	 *     wp woopayments disable-test-drive-account
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Command line arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		try {
			$result = $this->onboarding_service->disable_test_drive_account( [ 'from' => 'cli' ] );
			if ( true === $result ) {
				WP_CLI::success( 'Test drive account disabled successfully.' );
			} else {
				WP_CLI::error( 'Failed to disable test drive account.' );
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Failed to disable test drive account: ' . $e->getMessage() );
		}
	}
}
