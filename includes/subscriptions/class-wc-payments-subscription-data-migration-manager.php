<?php
/**
 * Class WC_Payments_Subscription_Data_Migration_Manager
 *
 * @package WooCommerce\Payments
 */

/**
 * Subscriptions upgrade/downgrade data migrations.
 */
class WC_Payments_Subscription_Data_Migration_Manager {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * The option key used to store whether the WC Subscriptions plugin is active or not.
	 */
	const WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION = 'wcpay_subscriptions_plugin_active';

	/**
	 * The WP option key used to store the migration state.
	 *
	 * @var string
	 */
	const MIGRATION_STATE_OPTION_KEY = 'wc_pay_subscription_migration_status';

	/**
	 * The value stored in the self::MIGRATION_STATE_OPTION_KEY WP option to indicate an upgrade is in progress.
	 *
	 * @var string
	 */
	const UPGRADING = 'upgrading';

	/**
	 * The background batch processors (migrators) to run on upgrade.
	 *
	 * @var WC_Payments_Subscriptions_Background_Migrator[]
	 */
	private $upgrade_migrators = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_migrators();

		// Before we initialise the batch processors, check if the plugin status has changed.
		$this->verify_migration_state();

		// Before we initialise the batch processors, check if the migrations have finished.
		$this->check_migration_status();
		$this->init_migrators();
	}

	/**
	 * Loads the background data migrators.
	 */
	private function load_migrators() {
		// Load the abstract data migrator class.
		include_once __DIR__ . '/data-migrators/class-wc-payments-subscriptions-background-migrator.php';
		include_once __DIR__ . '/data-migrators/class-wc-payments-subscriptions-upgrader.php';

		$this->upgrade_migrators[] = new WC_Payments_Subscriptions_Upgrader();
	}

	/**
	 * Checks if the store's WC Subscriptions plugin status has changed and if so whether we need to schedule upgrades.
	 */
	private function verify_migration_state() {
		$plugin_was_active = 'yes' === get_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION, 'no' );

		// If the plugin wasn't active but is now, check if we need to upgrade any subscriptions.
		if ( ! $plugin_was_active && $this->is_subscriptions_plugin_active() ) {
			$this->clear_migration_status();
			$this->maybe_schedule_upgrade();

			update_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION, 'yes' );
		} elseif ( $plugin_was_active && ! $this->is_subscriptions_plugin_active() ) {

			// If we were in the middle of an upgrade, make sure we cancel those jobs.
			if ( $this->is_upgrading() ) {
				$this->cancel_migrations( $this->upgrade_migrators );
			}

			$this->clear_migration_status();

			// Deleting the option is equivalent to the plugin not being active.
			delete_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION );
		}
	}

	/**
	 * Checks if the store's is upgrading and still has migrations scheduled.
	 */
	private function check_migration_status() {
		if ( $this->is_upgrading() ) {
			foreach ( $this->upgrade_migrators as $migrator ) {
				if ( ! $migrator->has_finished() ) {
					return;
				}
			}

			$this->clear_migration_status();
		}
	}

	/**
	 * Initializes the batch processors if we're upgrading.
	 */
	public function init_migrators() {
		if ( $this->is_upgrading() ) {
			foreach ( $this->upgrade_migrators as $migrator ) {
				$migrator->init();
			}
		}
	}

	/**
	 * Determines if there is an upgrade migration in progress.
	 *
	 * @return bool Whether the store is upgrading.
	 */
	public function is_upgrading() {
		return self::UPGRADING === get_option( self::MIGRATION_STATE_OPTION_KEY, '' );
	}

	/**
	 * Sets the stores migration status.
	 *
	 * @param string $status Sets the store's current migration status. Should be self::UPGRADING or self::DOWNGRADING.
	 */
	private function set_migration_status( $status ) {
		update_option( self::MIGRATION_STATE_OPTION_KEY, $status );
	}

	/**
	 * Clears the current migration status.
	 */
	private function clear_migration_status() {
		delete_option( self::MIGRATION_STATE_OPTION_KEY );
	}

	/**
	 * Schedules the upgrade background batch processors if the store is upgrading and has data in need of migration.
	 */
	public function maybe_schedule_upgrade() {
		// Skip if the store is already upgrading.
		if ( ! $this->is_upgrading() && $this->has_data_to_migrate( $this->upgrade_migrators ) ) {
			$this->set_migration_status( self::UPGRADING );

			foreach ( $this->upgrade_migrators as $migrator ) {
				$migrator->schedule_update();
			}
		}
	}

	/**
	 * Cancels all the background batch processors by migration type.
	 *
	 * @param WC_Payments_Subscriptions_Background_Migrator[] $migrators The set of migrators to cancel.
	 */
	private function cancel_migrations( $migrators ) {
		foreach ( $migrators as $migrator ) {
			$migrator->cancel_all_actions();
		}
	}

	/**
	 * Determines if there is data to migrate based on a type of upgrade/downgrade.
	 *
	 * @param WC_Payments_Subscriptions_Background_Migrator[] $migrators The set of migrators to check.
	 * @return bool Whether any of the migrators have data to migrate.
	 */
	private function has_data_to_migrate( $migrators ) {
		foreach ( $migrators as $migrator ) {
			if ( $migrator->has_items_to_update() ) {
				return true;
			}
		}

		return false;
	}
}
