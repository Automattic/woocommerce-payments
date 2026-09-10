<?php
/**
 * Validate saved checkout valuation inputs independently of Analytics.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** A qualified snapshot is a checkout valuation, never processor receipts. */
class CheckoutSalesSnapshot {
	/** Register the optional checkout valuation provider on a shared instance. */
	public function init_hooks(): void {
		add_filter( 'woocommerce_customer_history_sales_valuation', [ $this, 'provide_customer_history' ], 10, 2 );
	}

	/**
	 * Supply only the expressly requested checkout valuation policy.
	 *
	 * @param array $providers Existing provider responses.
	 * @param array $request Canonical order ID, reporting currency and policy.
	 * @return array Provider responses with explicit evidence and unavailable states.
	 */
	public function provide_customer_history( array $providers, array $request ): array {
		$id       = $request['order_id'] ?? null;
		$currency = $request['currency'] ?? null;
		if ( ! is_int( $id ) || $id <= 0 || ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || 'checkout' !== ( $request['policy'] ?? null ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return $providers;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce order-specific capability.
		if ( ! current_user_can( 'edit_shop_order', $id ) ) {
			return $providers;
		}
		try {
			$order = wc_get_order( $id );
			if ( ! $order instanceof \WC_Order || 'shop_order' !== $order->get_type() ) {
				return $providers;
			}
			$valuation = $this->read_after_refunds( $order, $currency );
		} catch ( \Throwable $error ) {
			$valuation = [ 'state' => 'unavailable' ];
		}
		$response = [
			'state'    => $valuation['state'],
			'order_id' => $id,
			'currency' => $currency,
			'policy'   => 'checkout',
		];
		if ( 'qualified' === $valuation['state'] ) {
			$response['total_minor']          = $valuation['total_minor'];
			$response['precision']            = $valuation['inputs']['precision'];
			$response['source']               = 'saved_checkout_rate';
			$response['original_total_minor'] = $valuation['original_total_minor'];
			$response['refunds_minor']        = $valuation['refunds_minor'];
		}
		$providers['woocommerce_payments'] = $response;
		return $providers;
	}

	/**
	 * Read a unique snapshot matching canonical order inputs and requested currency.
	 *
	 * @param \WC_Order $order Canonical order; caller authorizes access.
	 * @param string    $currency Requested reporting currency.
	 * @return array Qualified inputs or an explicit unavailable state.
	 */
	public function read( \WC_Order $order, string $currency ): array {
		$missing = [ 'state' => 'unavailable' ];
		if ( $order->get_id() <= 0 || 'shop_order' !== $order->get_type() || ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
			return $missing;
		}
		$rows = $order->get_meta( '_wcpay_checkout_sales_snapshot', false, 'edit' );
		if ( count( $rows ) !== 1 ) {
			return $missing;
		}
		$snapshot = reset( $rows )->value;
		if ( ! is_array( $snapshot ) || 1 !== ( $snapshot['version'] ?? null ) || 'checkout' !== ( $snapshot['basis'] ?? null ) || ! is_int( $snapshot['captured_at'] ?? null ) || $snapshot['captured_at'] <= 0 || ! is_array( $snapshot['inputs'] ?? null ) || ! is_string( $snapshot['fingerprint'] ?? null ) ) {
			return $missing;
		}
		$inputs = $snapshot['inputs'];
		if ( ! hash_equals( hash( 'sha256', wp_json_encode( $inputs ) ), $snapshot['fingerprint'] ) || ( $inputs['reporting_currency'] ?? null ) !== $currency || 'order_per_reporting' !== ( $inputs['rate_direction'] ?? null ) || ! is_int( $inputs['precision'] ?? null ) || $inputs['precision'] < 0 || $inputs['precision'] > 3 || ! is_string( $inputs['rate'] ?? null ) || ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $inputs['rate'] ) || ! is_finite( (float) $inputs['rate'] ) || (float) $inputs['rate'] <= 0 ) {
			return $missing;
		}
		$current = [
			'order_id'  => $order->get_id(),
			'order_key' => $order->get_order_key(),
			'currency'  => $order->get_currency( 'edit' ),
			'total'     => (string) $order->get_total( 'edit' ),
			'tax'       => (string) $order->get_total_tax( 'edit' ),
			'shipping'  => (string) $order->get_shipping_total( 'edit' ),
		];
		if ( '' === $current['order_key'] || $currency === $current['currency'] || ! preg_match( '/^[A-Z]{3}$/D', $current['currency'] ) ) {
			return $missing;
		}
		foreach ( $current as $key => $value ) {
			if ( ( $inputs[ $key ] ?? null ) !== $value ) {
				return [ 'state' => 'stale' ];
			}
		}
		foreach ( [ 'total', 'tax', 'shipping' ] as $field ) {
			if ( ! is_string( $inputs[ $field ] ) || ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $inputs[ $field ] ) ) {
				return $missing;
			}
		}
		$total_minor = ( new HistoricalSalesValuation() )->convert( $inputs['total'], $inputs['rate'], $inputs['precision'] );
		if ( null === $total_minor ) {
			return $missing;
		}
		return [
			'total_minor' => $total_minor,
			'state'       => 'qualified',
			'basis'       => 'checkout',
			'inputs'      => $inputs,
		];
	}
	/**
	 * Read order value after canonical refunds using cumulative reversal rounding.
	 *
	 * This is a current sales valuation, not historical processor refund activity.
	 * Refund IDs provide a stable allocation order within this observation.
	 *
	 * @param \WC_Order $order Canonical order; caller authorizes access.
	 * @param string    $currency Requested reporting currency.
	 * @return array Qualified remaining value and reversals, or unavailable.
	 */
	public function read_after_refunds( \WC_Order $order, string $currency ): array {
		global $wpdb;
		$result = $this->read( $order, $currency );
		if ( 'qualified' !== $result['state'] ) {
			return $result;
		}
		$missing  = [ 'state' => 'unavailable' ];
		$original = $this->original_units( $result['inputs']['total'] );
		if ( null === $original ) {
			return $missing;
		}
		if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core-owned table name; parent is prepared.
			$sql = $wpdb->prepare( "SELECT id FROM $table WHERE type = 'shop_order_refund' AND status NOT IN ('trash', 'auto-draft') AND parent_order_id = %d ORDER BY id ASC", $order->get_id() );
		} else {
			$sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order_refund' AND post_status NOT IN ('trash', 'auto-draft') AND post_parent = %d ORDER BY ID ASC", $order->get_id() );
		}
		// Query canonical membership directly: the generic HPOS query can swallow exceptions.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above for the active storage mode.
		$refunds = $wpdb->get_col( $sql );
		if ( ! is_array( $refunds ) || $wpdb->last_error ) {
			return $missing;
		}
		$amounts = [];
		foreach ( $refunds as $refund_id ) {
			if ( ! is_numeric( $refund_id ) || (int) $refund_id <= 0 ) {
				return $missing;
			}
			try {
				$refund = wc_get_order( (int) $refund_id );
			} catch ( \Throwable $error ) {
				return $missing;
			}
			if ( $wpdb->last_error ) {
				return $missing;
			}
			if ( ! $refund instanceof \WC_Order_Refund || $refund->get_id() <= 0 || $refund->get_parent_id() !== $order->get_id() || $refund->get_currency( 'edit' ) !== $result['inputs']['currency'] || isset( $amounts[ $refund->get_id() ] ) ) {
				return $missing;
			}
			$amount = $this->original_units( (string) $refund->get_amount( 'edit' ) );
			if ( null === $amount ) {
				return $missing;
			}
			$amounts[ $refund->get_id() ] = $amount;
		}
		ksort( $amounts, SORT_NUMERIC );
		$previous    = 0;
		$remaining   = $result['total_minor'];
		$allocations = [];
		$allocator   = new HistoricalSalesRefundAllocation();
		foreach ( $amounts as $id => $amount ) {
			$reversal = 0 === $original && 0 === $amount ? 0 : $allocator->allocate( $original, $result['total_minor'], $previous, $amount );
			if ( null === $reversal ) {
				return $missing;
			}
			$previous          += $amount;
			$remaining         -= $reversal;
			$allocations[ $id ] = $reversal;
		}
		$result['original_total_minor'] = $result['total_minor'];
		$result['total_minor']          = $remaining;
		$result['refunds_minor']        = $allocations;
		return $result;
	}

	/**
	 * Scale original values exactly to thousandths for refund ratios.
	 *
	 * @param string $amount Nonnegative amount with at most three significant decimals.
	 * @return int|null Exact units, or unsupported precision/overflow.
	 */
	private function original_units( string $amount ): ?int {
		if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3}0*)?$/D', $amount ) ) {
			return null;
		}
		return ( new HistoricalSalesValuation() )->convert( $amount, '1', 3 );
	}
}
