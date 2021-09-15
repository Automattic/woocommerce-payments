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
	 * @var WCS_Background_Repairer[]
	 */
	private $upgrade_background_processors = [];

	/**
	 * The background batch processors to run on downgrade.
	 *
	 * @var WCS_Background_Repairer[]
	 */
	private $downgrade_background_processors = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_subscriptions_activated', [ $this, 'maybe_schedule_upgrade' ] );
		add_action( 'woocommerce_subscriptions_deactivated', [ $this, 'maybe_schedule_downgrade' ] );

		// Before we initialise the batch processors, validate that the current migration status is correct.
		$this->validate_migration_status();
		$this->init_background_processors();
	}

	/**
	 * Validates that the current store's migration status matches the WC Subscriptions plugin active status.
	 *
	 * If the migration status is inconsistant with the plugin status, this function also checks if we need to schedule the reverse migration.
	 */
	private function validate_migration_status() {
		// If the store is upgrading, make sure WC Subscriptions is still active.
		if ( $this->is_upgrading() && ! $this->is_subscriptions_plugin_active() ) {
			$this->clear_migration_status();
			$this->maybe_schedule_downgrade();
		} elseif ( $this->is_downgrading() && $this->is_subscriptions_plugin_active() ) {
			// If the store is downgrading, and the WC Subscriptions plugin is active.
			$this->clear_migration_status();
			$this->maybe_schedule_upgrade();
		}
	}

	/**
	 * Initialises the batch processors that apply to the type of data migration the store is undergoing.
	 */
	private function init_background_processors() {
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
	 * Determines if there is data to migrate based on a type of upgrade/downgrade.
	 *
	 * @param string $migration_type The type of migration to check. Can be 'downgrade' or 'upgrade'.
	 * @return bool Whether there is data that needs migrating based on the type of migration.
	 */
	private function has_data_to_migrate( $migration_type ) {
		foreach ( $this->get_background_processors_by_type( $migration_type ) as $batch_processor ) {
			// TODO: this function is currently inaccessible so will need to change pending Shendy's work.
			if ( ! empty( $batch_processor->get_items_to_repair() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the background processors by migration type.
	 *
	 * @param string $migration_type The type of migration to check. Can be 'downgrade' or 'upgrade'.
	 * @return WCS_Background_Repairer[] The background batch processors.
	 */
	private function get_background_processors_by_type( $migration_type ) {
		switch ( $migration_type ) {
			case self::UPGRADING:
				return $this->upgrade_background_processors;
			case self::DOWNGRADING:
				return $this->downgrade_background_processor;
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
