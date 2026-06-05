<?php
/**
 * Test double for the WooCommerce fulfillment data store.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Returns whatever was set in the public static $next_result property.
 * Used by WooPay_Fulfillments_API_Provider tests to control what
 * read_fulfillments() returns without spinning up the real DataStore.
 */
class Fake_Fulfillments_Data_Store {
	/**
	 * The fulfillments to return on the next read_fulfillments() call.
	 *
	 * @var array
	 */
	public static $next_result = [];

	/**
	 * Counter of how many times read_fulfillments() has been called.
	 * Used by tests verifying request-level caching.
	 *
	 * @var int
	 */
	public static $read_call_count = 0;

	/**
	 * Stub of FulfillmentsDataStore::read_fulfillments().
	 *
	 * @param string $entity_type Class name of the parent entity (unused).
	 * @param string $entity_id   ID of the parent entity (unused).
	 * @return array
	 */
	public function read_fulfillments( $entity_type, $entity_id ) {
		++self::$read_call_count;
		return self::$next_result;
	}
}
