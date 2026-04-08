<?php
/**
 * Gift Cards helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// phpcs:disable

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class WC_GC_Gift_Card_Data.
 *
 * Mock class for test usage.
 */
class WC_GC_Gift_Card_Data {
	private $balance = 0;

	public function __construct( $balance ) {
		$this->balance = $balance;
	}

	public function get_balance() {
		return $this->balance;
	}
}

/**
 * Class WC_GC_Account.
 *
 * Mock class for test usage.
 */
class WC_GC_Account {
	public function get_active_giftcards( $user_id ) {
		$data = new WC_GC_Gift_Card_Data( $user_id );

		return [ $data ];
	}
}

/**
 * Class WC_Gift_Cards.
 *
 * Mock class for test usage.
 */
class WC_Gift_Cards {
	/**
	 * @var WC_GC_Emails
	 */
	public $account;

	public function __construct() {
		$this->account = new WC_GC_Account();
	}
}

/**
 * Class WC_GC_Checkout_Blocks_Integration.
 *
 * Mock class for test usage.
 */
class WC_GC_Checkout_Blocks_Integration implements IntegrationInterface {
	public function get_name() {
		return 'wc-gift-cards-blocks';
	}

	public function initialize() {
	}

	public function get_script_handles() {
	}

	public function get_script_data() {
		return [
			'account_orders_link' => add_query_arg( [ 'wc_gc_show_pending_orders' => 'yes' ], wc_get_account_endpoint_url( 'orders' ) ),
		];
	}

	public function get_editor_script_handles() {
	}
}

function WC_GC() {
	return new WC_Gift_Cards();
}
