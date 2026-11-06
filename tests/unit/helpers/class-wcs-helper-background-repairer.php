<?php
/**
 * Subscription WCS_Background_Repairer helper.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WCS_Background_Repairer.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WCS_Background_Repairer {

	/**
	 * @var string The hook used to schedule background repairs for a specific object.
	 */
	protected $repair_hook;

	public function init() {
		// Do nothing.
	}

	protected function unschedule_background_updates() {
		// Do nothing.
	}
}
