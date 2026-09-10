<?php
/**
 * Read historical transaction rows without claiming a complete payment total.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Local evidence adapter; callers must authorize access to the requested order. */
class PaymentActivityReader {
	/**
	 * Historical receipt discovery.
	 *
	 * @var PaymentReceiptIndex
	 */
	private $index;
	/**
	 * Exact published observations.
	 *
	 * @var PaymentReportRepository
	 */
	private $reports;

	/**
	 * Construct without fetching provider data.
	 *
	 * @param PaymentReceiptIndex     $index Receipt discovery.
	 * @param PaymentReportRepository $reports Published evidence.
	 */
	public function __construct( PaymentReceiptIndex $index, PaymentReportRepository $reports ) {
		$this->index   = $index;
		$this->reports = $reports;
	}

	/** Register the prototype Customer History provider on this shared instance. */
	public function init_hooks(): void {
		add_filter( 'woocommerce_customer_history_payment_activity', [ $this, 'provide_customer_history' ], 10, 2 );
		add_action( 'woocommerce_customer_history_payment_activity_controls', [ $this, 'render_refresh_control' ], 10, 4 );
	}

	/**
	 * Render an explicit refresh request without fetching payment data.
	 *
	 * @param int    $order_id Canonical order ID.
	 * @param string $currency Reporting currency.
	 * @param bool   $test_mode Explicit payment mode.
	 * @param string $provider Target provider.
	 */
	public function render_refresh_control( int $order_id, string $currency, bool $test_mode, string $provider ): void {
		if ( 'woocommerce_payments' !== $provider || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce order-specific meta capability.
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || 'shop_order' !== $order->get_type() ) {
			return;
		}
		wp_enqueue_script( 'wp-api-fetch' );
		wp_add_inline_script(
			'wp-api-fetch',
			<<<'JS'
(function () {
	if (window.wcpayHistoryRefreshBound) return;
	window.wcpayHistoryRefreshBound = true;
	document.addEventListener('click', function (event) {
		var button = event.target.closest('.wcpay-history-refresh');
		if (!button || button.disabled) return;
		button.disabled = true;
		var status = button.parentNode.querySelector('[role="status"]');
		status.textContent = button.dataset.pending;
		window.wp.apiFetch({
			url: button.dataset.url,
			method: 'POST',
			headers: {'X-WP-Nonce': button.dataset.nonce},
			data: {currency: button.dataset.currency, test_mode: button.dataset.mode === 'test'}
		}).then(function (data) {
			status.textContent = data && data.state === 'queued' && data.coverage === 'unknown' && Number.isInteger(data.action_id) && data.action_id > 0 ? button.dataset.queued : button.dataset.uncertain;
		}).catch(function (error) {
			var rejected = error && error.data && Number.isInteger(error.data.status) && error.data.status >= 400 && error.data.status < 500;
			status.textContent = rejected ? button.dataset.failed : button.dataset.uncertain;
			button.disabled = !rejected;
		});
	});
}());
JS
		);
		echo '<p><button type="button" class="button wcpay-history-refresh"';
		foreach ( [
			'url'       => rest_url( 'wc/v3/payments/orders/' . $order_id . '/payment-history/refresh' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'currency'  => $currency,
			'mode'      => $test_mode ? 'test' : 'live',
			'pending'   => __( 'Queueing refresh…', 'woocommerce-payments' ),
			'queued'    => __( 'Refresh queued. Reload this order later to see available payment activity.', 'woocommerce-payments' ),
			'failed'    => __( 'Refresh could not be queued. Existing activity is unchanged.', 'woocommerce-payments' ),
			'uncertain' => __( 'Refresh status could not be confirmed. Reload before trying again.', 'woocommerce-payments' ),
		] as $key => $value ) {
			echo ' data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		echo '>' . esc_html__( 'Refresh payment activity', 'woocommerce-payments' ) . '</button><br><span role="status" aria-live="polite"></span></p>';
	}

	/**
	 * Supply a bounded page of local evidence for an authorized admin request.
	 *
	 * This prototype hook is implemented together with the core reporting view;
	 * it is not an existing public Analytics ingestion API. No provider fetch runs.
	 * Storage remains bound to the construction site: switched-blog reads are
	 * refused by the index and must not be presented as an empty payment history.
	 *
	 * @param array $providers Other providers' responses, preserved by key.
	 * @param array $request Explicit order, currency, mode and receipt cursor.
	 * @return array Provider responses. Invalid or unauthorized requests add nothing.
	 */
	public function provide_customer_history( array $providers, array $request ): array {
		if ( ! in_array( $request['provider'] ?? '', [ '', 'woocommerce_payments' ], true ) ) {
			return $providers;
		}
		if ( ! is_int( $request['order_id'] ?? null ) || $request['order_id'] <= 0 || ! is_string( $request['currency'] ?? null ) || ! preg_match( '/^[A-Z]{3}$/D', $request['currency'] ) || ! is_bool( $request['test_mode'] ?? null ) || ! is_string( $request['after'] ?? null ) || ! preg_match( '/^(?:[a-f0-9]{64})?$/D', $request['after'] ) ) {
			return $providers;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce's order-specific meta capability supports both storage engines.
		if ( ! current_user_can( 'edit_shop_order', $request['order_id'] ) ) {
			return $providers;
		}
		$order = wc_get_order( $request['order_id'] );
		if ( ! $order || 'shop_order' !== $order->get_type() ) {
			return $providers;
		}
		$providers['woocommerce_payments'] = $this->read_for_order( $request['order_id'], $request['currency'], $request['test_mode'], $request['after'] );
		return $providers;
	}

	/**
	 * Read one bounded discovery page; no network or order mutation occurs.
	 *
	 * Test and live payments are never combined. Pagination traverses known
	 * receipts in both modes; callers must follow has_more even on an empty page.
	 * Unknown aggregate coverage is independent of individual row qualification.
	 *
	 * @param int    $order_id Authorized canonical order ID.
	 * @param string $currency Required reporting currency.
	 * @param bool   $test_mode Explicit mode of the requested view.
	 * @param string $after Receipt discovery cursor.
	 * @return array Activity attempts, never a complete order or customer total.
	 */
	public function read_for_order( int $order_id, string $currency, bool $test_mode, string $after = '' ): array {
		if ( ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
			return [ 'state' => 'invalid' ];
		}
		$page = $this->index->find_for_order( $order_id, 10, $after );
		if ( ! in_array( $page['state'], [ 'known', 'unknown' ], true ) ) {
			return $page;
		}
		$attempts = [];
		foreach ( $page['receipts'] as $locator ) {
			$scope = $locator['scope'];
			if ( $scope['test_mode'] !== $test_mode ) {
				continue;
			}
			$report  = $this->reports->read_observations( $scope, $currency );
			$attempt = [
				'intent_id' => $scope['event_id'],
				'state'     => $report['state'],
				'rows'      => [],
			];
			if ( 'observed' === $report['state'] ) {
				$rows                 = $this->qualified_rows( $report, $scope, $currency );
				$attempt['state']     = null === $rows ? 'unqualified' : 'observed';
				$attempt['rows']      = $rows ?? [];
				$attempt['revision']  = $report['revision'];
				$attempt['retrieval'] = $report['retrieval'];
			}
			$attempts[] = $attempt;
		}
		return [
			'state'       => $attempts ? 'known' : 'unknown',
			'coverage'    => 'unknown',
			'total'       => null,
			'currency'    => $currency,
			'test_mode'   => $test_mode,
			'attempts'    => $attempts,
			'has_more'    => $page['has_more'],
			'next_cursor' => $page['next_cursor'],
		];
	}

	/**
	 * Translate supported evidence into signed gross and independently qualified net rows.
	 *
	 * @param array  $report Exact report observations.
	 * @param array  $scope Historical intent scope.
	 * @param string $currency Required currency.
	 * @return array|null Rows, or null if any monetary evidence is unqualified.
	 */
	public function qualified_rows( array $report, array $scope, string $currency ): ?array {
		$receipt = $report['events'][ $scope['event_id'] ]['event'] ?? [];
		if ( 'intent_context' !== ( $receipt['kind'] ?? null ) || ! is_string( $receipt['charge_id'] ?? null ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $receipt['charge_id'] ) || ! is_int( $receipt['original_amount'] ?? null ) || $receipt['original_amount'] <= 0 || ! is_string( $receipt['original_currency'] ?? null ) || ! preg_match( '/^[A-Z]{3}$/D', $receipt['original_currency'] ) ) {
			return null;
		}
		$rows = [];
		foreach ( $report['events'] as $id => $source ) {
			$event = $source['event'];
			$kind  = $event['kind'] ?? null;
			if ( in_array( $kind, [ 'intent_context', 'refund_collection', 'refund_membership' ], true ) ) {
				continue;
			}
			if ( ! in_array( $kind, [ 'capture', 'refund', 'refund_failure_reversal' ], true ) || isset( $event['state'] ) ) {
				return null;
			}
			$evidence = $event['evidence'] ?? [];
			$capture  = 'capture' === $kind;
			$charge   = $capture ? ( $evidence['charge_id'] ?? null ) : ( $event['charge_id'] ?? null );
			$event_id = $capture ? ( $evidence['balance_transaction_id'] ?? null ) : ( $evidence['id'] ?? null );
			$created  = $capture ? ( $evidence['source_created'] ?? null ) : ( $evidence['created'] ?? null );
			$amount   = $evidence['amount'] ?? null;
			if ( $charge !== $receipt['charge_id'] || $event_id !== $id || ( $evidence['currency'] ?? null ) !== $currency || ! is_int( $amount ) || ! is_int( $created ) || $created <= 0 || ( 'refund' === $kind ? $amount >= 0 : $amount <= 0 ) ) {
				return null;
			}
			if ( $capture && ( 'ready' !== ( $evidence['state'] ?? null ) || 'captured_payment_gross' !== ( $evidence['basis'] ?? null ) || ( $evidence['original_amount'] ?? null ) !== ( $receipt['original_amount'] ?? null ) || ( $evidence['original_currency'] ?? null ) !== ( $receipt['original_currency'] ?? null ) ) ) {
				return null;
			}
			if ( ! $capture && ( ( $evidence['kind'] ?? null ) !== $kind || ! is_string( $event['refund_id'] ?? null ) || ( $event['original_currency'] ?? null ) !== ( $receipt['original_currency'] ?? null ) ) ) {
				return null;
			}
			if ( $capture && $currency === $receipt['original_currency'] && $amount !== $receipt['original_amount'] ) {
				return null;
			}
			if ( ! $capture && ( ! is_int( $event['original_amount'] ?? null ) || $event['original_amount'] <= 0 || ! preg_match( '/^re_[a-zA-Z0-9]+$/D', $event['refund_id'] ) || ( 'refund' === $kind && $currency === $receipt['original_currency'] && -$event['original_amount'] !== $amount ) ) ) {
				return null;
			}
			$fee     = $evidence[ $capture ? 'fee_amount' : 'fee' ] ?? null;
			$net     = $evidence[ $capture ? 'net_amount' : 'net' ] ?? null;
			$has_net = ( ! $capture || 'ready' === ( $evidence['net_state'] ?? null ) ) && is_int( $fee ) && is_int( $net ) && $amount - $fee === $net;
			$rows[]  = [
				'net_state'    => $has_net ? 'ready' : 'unavailable',
				'fee_amount'   => $has_net ? $fee : null,
				'net_amount'   => $has_net ? $net : null,
				'kind'         => $kind,
				'source_id'    => $id,
				'revision'     => $source['revision'],
				'amount'       => $amount,
				'currency'     => $currency,
				'precision'    => \WC_Payments_Utils::get_stripe_minor_unit_for_currency( $currency ),
				'created'      => $created,
				'funds_status' => in_array( $evidence['funds_status'] ?? null, [ 'pending', 'available' ], true ) ? $evidence['funds_status'] : 'unknown',
			];
		}
		return $rows;
	}
}
