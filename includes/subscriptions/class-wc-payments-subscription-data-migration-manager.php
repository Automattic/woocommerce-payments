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
	 * The value stored in the self::MIGRATION_STATE_OPTION_KEY WP option to indicate a downgrade is in progress.
	 *
	 * @var string
	 */
	const DOWNGRADING = 'downgrading';

	/**
	 * The background batch processors to run on upgrade.
	 *
	 * @var WC_Payments_Subscriptions_Background_Migrator[]
	 */
	private $upgrade_background_migrators = [];

	/**
	 * The background batch processors to run on downgrade.
	 *
	 * @var WC_Payments_Subscriptions_Background_Migrator[]
	 */
	private $downgrade_background_migrators = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_background_processors();

		// Before we initialise the batch processors, check if the plugin status has changed.
		$this->check_for_migrations();
		$this->init_background_processors();
	}

	/**
	 * Loads the background processors for the current type of migration.
	 */
	private function load_background_processors() {
		// Load the abstract data migrator class.
		include_once __DIR__ . '/data-migrators/class-wc-payments-subscriptions-background-migrator.php';
		include_once __DIR__ . '/data-migrators/class-wc-payments-manual-subscriptions-downgrader.php';

		$this->downgrade_background_migrators[] = new WC_Payments_Manual_Subscriptions_Downgrader();
	}

	/**
	 * Checks if the store's WC Subscriptions plugin status has changed and if so whether we need to schedule data migrations.
	 */
	public function check_for_migrations() {
		$plugin_was_active = 'yes' === get_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION, 'no' );

		// If the plugin wasn't active but is now, check if we have subscriptions which need migrating.
		if ( ! $plugin_was_active && $this->is_subscriptions_plugin_active() ) {

			// If we were in the middle of a downgrade, make sure we cancel those jobs first.
			if ( $this->is_downgrading() ) {
				$this->cancel_background_migrators( self::DOWNGRADING );
			}

			$this->clear_migration_status();
			$this->maybe_schedule_upgrade();

			update_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION, 'yes' );
		} elseif ( $plugin_was_active && ! $this->is_subscriptions_plugin_active() ) {

			// If we were in the middle of an upgrade, make sure we cancel those jobs first.
			if ( $this->is_upgrading() ) {
				$this->cancel_background_migrators( self::UPGRADING );
			}

			$this->clear_migration_status();
			$this->maybe_schedule_downgrade();

			// Deleting the option is equivalent to the plugin not being active.
			delete_option( self::WC_SUBSCRIPTIONS_PLUGIN_ACTIVE_OPTION );
		}
	}

	/**
	 * Initializes the batch processors that apply to the type of data migration the store is undergoing.
	 */
	public function init_background_processors() {
		$migration_type = $this->get_migration_type();

		if ( $migration_type ) {
			// Initialise the background processors for the type of migration.
			foreach ( $this->get_background_processors_by_type( $migration_type ) as $batch_processor ) {
				$batch_processor->init();
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
	 * Determines if there is a downgrade migration in progress.
	 *
	 * @return bool Whether the store is downgrading.
	 */
	public function is_downgrading() {
		return self::DOWNGRADING === get_option( self::MIGRATION_STATE_OPTION_KEY, '' );
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
		if ( $this->is_upgrading() ) {
			return;
		}

		if ( $this->has_data_to_migrate( self::UPGRADING ) ) {
			$this->set_migration_status( self::UPGRADING );

			foreach ( $this->get_background_processors_by_type( self::UPGRADING ) as $batch_processor ) {
				$batch_processor->schedule_repair();
			}
		}
	}

	/**
	 * Schedules the downgrade background batch processors if the store is downgrading and has data in need of migration.
	 */
	public function maybe_schedule_downgrade() {
		// Skip if the store is already downgrading.
		if ( $this->is_downgrading() ) {
			return;
		}

		if ( $this->has_data_to_migrate( self::DOWNGRADING ) ) {
			$this->set_migration_status( self::DOWNGRADING );

			foreach ( $this->get_background_processors_by_type( self::DOWNGRADING ) as $batch_processor ) {
				$batch_processor->schedule_repair();
			}
		}
	}

	/**
	 * Cancels all the background batch processors by migration type.
	 *
	 * @param string $migration_type The type of migration to check. Can be 'downgrade' or 'upgrade'.
	 */
	private function cancel_background_migrators( $migration_type ) {
		foreach ( $this->get_background_processors_by_type( $migration_type ) as $batch_processor ) {
			$batch_processor->unschedule_all_actions();
		}
	}

	/**
	 * Determines if there is data to migrate based on a type of upgrade/downgrade.
	 *
	 * @param string $migration_type The type of migration to check. Can be 'downgrade' or 'upgrade'.
	 * @return bool Whether there is data that needs migrating based on the type of migration.
	 */
	private function has_data_to_migrate( $migration_type ) {
		foreach ( $this->get_background_processors_by_type( $migration_type ) as $batch_processor ) {
			if ( $batch_processor->has_items_to_update() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the background processors by migration type.
	 *
	 * @param string $migration_type The type of migration to check. Can be 'downgrade' or 'upgrade'.
	 * @return WC_Payments_Subscriptions_Background_Migrator[] The background batch processors.
	 */
	private function get_background_processors_by_type( $migration_type ) {
		switch ( $migration_type ) {
			case self::UPGRADING:
				return $this->upgrade_background_migrators;
			case self::DOWNGRADING:
				return $this->downgrade_background_migrators;
			default:
				return [];
		}
	}

	/**
	 * Gets the site's current migration type.
	 *
	 * @return string The migration type. Can be 'upgrade', 'downgrade' or '' (empty string) if no migration is in progress.
	 */
	private function get_migration_type() {
		if ( $this->is_upgrading() ) {
			return self::UPGRADING;
		} elseif ( $this->is_downgrading() ) {
			return self::DOWNGRADING;
		} else {
			return '';
		}
	}
}
