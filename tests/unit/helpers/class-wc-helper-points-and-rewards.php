<?php
/**
 * Points & Rewards helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class WC_Points_Rewards.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Points_Rewards {

}

/**
 * Class WC_Points_Rewards_Integration.
 *
 * Mock class for test usage.
 */
class WC_Points_Rewards_Integration implements IntegrationInterface {
	public function get_name() {
		return 'points-and-rewards';
	}

	public function initialize() {
	}

	public function get_script_handles() {
	}

	public function get_script_data() {
		return [
			'woocommerce-points-and-rewards-blocks' => 'active',
			'points_available'                      => 0,
			'minimum_points_amount'                 => 0,
			'partial_redemption_enabled'            => true,
			'points_label_singular'                 => 'Point',
			'points_label_plural'                   => 'Points',
		];
	}

	public function get_editor_script_handles() {
	}
}

/**
 * Class WC_Points_Rewards_Manager.
 *
 * Mock class for test usage.
 */
class WC_Points_Rewards_Manager {
	public static function get_users_points( $user_id ) {
		return $user_id;
	}
}
