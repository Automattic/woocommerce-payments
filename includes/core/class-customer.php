<?php
namespace WCPay\Core;

use WC_Payments;

class Customer {
	protected $user_id;
	protected $stripe_id;

	protected static function get_service() : \WC_Payments_Customer_Service {
		return WC_Payments::get_customer_service();
	}

	public static function from_user_id( int $user_id ) : Customer {
		$customer_id = static::get_service()->get_customer_id_by_user_id( $user_id );
		return new static( $user_id, $customer_id );
	}

	/**
	 * @param string $customer_id Stripe customer ID
	 * @return Customer|null
	 */
	public static function from_stripe_id( string $customer_id ) {
		global $wpdb;

		$prefix = \WC_Payments::is_network_saved_cards_enabled()
			? $wpdb->get_blog_prefix()
			: '';

		$users = get_users(
			[
				'meta_key'   => $prefix . static::get_service()->get_customer_id_option(),
				'meta_value' => $customer_id,
				'number'     => 1,
			]
		 );

		if ( empty( $users ) ) {
			return null;
		}

		return new static( $users[0]->ID, $customer_id );
	}

	private function __construct( $user_id = null, $stripe_id = null ) {
		$this->user_id   = $user_id;
		$this->stripe_id = $stripe_id;
	}

	protected function get_user() : \WP_User {
		return get_user_by( 'id', $this->user_id );
	}

	public function create( $customer_data ) {
		// Possible side-effect: We're storing the customer ID in the session, thi smight be unstable.
		$stripe_id = static::get_service()->create_customer_for_user( $this->get_user(), $customer_data );

		$this->stripe_id = $stripe_id;
	}

	public function update( $customer_data ) {
		static::get_service()->update_customer_for_user( $this->stripe_id, $this->get_user(), $customer_data );
	}

	public function set_default_payment_method( string $payment_method_id ) {
		static::get_service()->set_default_payment_method_for_customer( $this->stripe_id, $payment_method_id );
	}

	public function get_payment_methods( $type = 'card' ) {
		// Maybe we should map those to a Payment Method object?
		return static::get_service()->get_payment_methods_for_customer( $this->stripe_id, $type );
	}

	public function clear_cached_payment_methods() {
		static::get_service()->clear_cached_payment_methods_for_user( $this->user_id );
	}

	public function update_stripe_id( $stripe_id ) {
		static::get_service()->update_for_customer_id( $this->user_id, $stripe_id );
		$this->stripe_id = $stripe_id;
	}

	/**
	 * Those methods do not belong in the customer service:
	 *
	 * - update_payment_method_with_billing_details_from_order
	 * - get_customer_id_for_order
	 * - add_customer_id_to_user (it's specific to the service, although it doesn't seem to belong there.)
	 * - Similar for get_prepared_customer_data
	 */
}
