<?php
/**
 * Bounded asynchronous discovery of historical payment candidates.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Scheduling does not certify historical coverage or monetary totals. */
class PaymentAttemptDiscoveryScheduler {
	const HOOK = 'wcpay_discover_payment_attempts';
	/**
	 * Discovery worker.
	 *
	 * @var PaymentAttemptDiscovery
	 */
	private $discovery;
	/**
	 * Progress store.
	 *
	 * @var PaymentEventRepository
	 */
	private $repository;
	/**
	 * Candidate queue.
	 *
	 * @var PaymentEventRecoveryScheduler
	 */
	private $candidates;

	/**
	 * Construct without scheduling work.
	 *
	 * @param PaymentAttemptDiscovery       $discovery Worker.
	 * @param PaymentEventRepository        $repository Progress.
	 * @param PaymentEventRecoveryScheduler $candidates Candidate queue.
	 */
	public function __construct( PaymentAttemptDiscovery $discovery, PaymentEventRepository $repository, PaymentEventRecoveryScheduler $candidates ) {
		$this->discovery  = $discovery;
		$this->repository = $repository;
		$this->candidates = $candidates;
	}

	/**
	 * Start an authorized refresh without changing orders or payment transactions.
	 *
	 * HTTP callers must separately validate their request nonce.
	 *
	 * @param int    $order_id Canonical order.
	 * @param string $currency Reporting currency.
	 * @param bool   $test_mode Explicit payment mode to discover.
	 * @return array Queued generation identity or failure state.
	 * @throws \RuntimeException When queue persistence fails.
	 */
	public function start( int $order_id, string $currency, bool $test_mode ): array {
		// WooCommerce maps this order-specific meta capability.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			return [ 'state' => 'forbidden' ];
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || 'shop_order' !== $order->get_type() || ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( ! $this->repository->is_schema_compatible() ) {
			return [ 'state' => 'unavailable' ];
		}
		$context = $this->discovery->resolve_context( $test_mode );
		if ( [] === $context ) {
			return [ 'state' => 'unavailable' ];
		}
		$scope = [
			'account_id' => $context['account_id'],
			'site_id'    => $context['site_id'],
			'test_mode'  => $test_mode,
			'order_id'   => $order_id,
			'event_id'   => 'discovery_' . str_replace( '-', '', wp_generate_uuid4() ),
		];
		$id    = $this->enqueue( $scope, $currency );
		return [
			'state'     => 'queued',
			'coverage'  => 'unknown',
			'action_id' => $id,
			'scope'     => $scope,
		];
	}

	/**
	 * Queue an authorized generation.
	 *
	 * @param array  $scope Immutable account/site/mode/order/generation.
	 * @param string $currency Reporting currency.
	 * @return int Action ID, or zero for a duplicate.
	 * @throws \RuntimeException When arguments or scheduling fail.
	 */
	public function enqueue( array $scope, string $currency ): int {
		$this->validate( $scope, $currency, 0, 0 );
		return $this->schedule( [ $scope, $currency, 0, 0 ], 0 );
	}

	/**
	 * Process one page and retain bounded retries.
	 *
	 * @param array  $scope Authorized generation.
	 * @param string $currency Reporting currency.
	 * @param int    $step Zero-based worker step.
	 * @param int    $attempt Retry index.
	 * @throws \RuntimeException On invalid, exhausted, or truncated work.
	 */
	public function run( array $scope, string $currency, int $step = 0, int $attempt = 0 ): void {
		$this->validate( $scope, $currency, $step, $attempt );
		try {
			$context = $this->discovery->resolve_context( $scope['test_mode'] );
			$result  = $this->discovery->run_step( $scope, $currency, $context, $this->repository, $this->candidates );
		} catch ( \Throwable $exception ) {
			$result = [ 'state' => 'incomplete' ];
		}
		if ( 'queued' === ( $result['state'] ?? null ) ) {
			if ( false === ( $result['has_more'] ?? null ) ) {
				return; }
			if ( true !== ( $result['has_more'] ?? null ) || ! is_int( $result['next_page'] ?? null ) || $step >= 19 ) {
				throw new \RuntimeException( 'Payment discovery remains truncated or invalid.' );
			}
			$this->schedule( [ $scope, $currency, $step + 1, 0 ], 0 );
			return;
		}
		if ( 'invalid' === ( $result['state'] ?? null ) || $attempt >= 3 ) {
			throw new \RuntimeException( 'Payment discovery could not finish.' );
		}
		$delays = [ 60, 300, 1800 ];
		$this->schedule( [ $scope, $currency, $step, $attempt + 1 ], $delays[ $attempt ] );
	}

	/**
	 * Validate job boundaries before resolving any connection.
	 *
	 * @param array  $scope Generation.
	 * @param string $currency Currency.
	 * @param int    $step Step.
	 * @param int    $attempt Attempt.
	 * @throws \RuntimeException When invalid.
	 */
	private function validate( array $scope, string $currency, int $step, int $attempt ): void {
		if ( 5 !== count( $scope ) || ! is_string( $scope['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $scope['account_id'] ) || ! is_int( $scope['site_id'] ?? null ) || $scope['site_id'] <= 0 || ! is_int( $scope['order_id'] ?? null ) || $scope['order_id'] <= 0 || ! is_bool( $scope['test_mode'] ?? null ) || ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^discovery_[a-zA-Z0-9]+$/D', $scope['event_id'] ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) || $step < 0 || $step > 19 || $attempt < 0 || $attempt > 3 ) {
			throw new \RuntimeException( 'Invalid payment discovery job.' );
		}
	}

	/**
	 * Enqueue uniquely, distinguishing failure from already pending work.
	 *
	 * @param array $args Job arguments.
	 * @param int   $delay Delay in seconds.
	 * @return int Action ID or zero for duplicate.
	 * @throws \RuntimeException When queue persistence fails.
	 */
	private function schedule( array $args, int $delay ): int {
		ksort( $args[0] );
		$identity = $args;
		$group    = 'wcpay-discovery-' . hash( 'sha256', wp_json_encode( $identity ) );
		$id       = $delay ? as_schedule_single_action( time() + $delay, self::HOOK, $args, $group, true ) : as_enqueue_async_action( self::HOOK, $args, $group, true );
		if ( ! $id && ! as_has_scheduled_action( self::HOOK, $args, $group ) ) {
			throw new \RuntimeException( 'Payment discovery could not be scheduled.' );
		}
		return $id;
	}
}
