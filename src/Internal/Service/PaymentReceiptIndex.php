<?php
/**
 * Immutable reverse lookup from historical charges to recorded intent receipts.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** An index is a locator; the immutable receipt remains the evidence. */
class PaymentReceiptIndex {
	/**
	 * Database connection.
	 *
	 * @var \wpdb
	 */
	private $db;
	/**
	 * Receipt evidence.
	 *
	 * @var PaymentEventRepository
	 */
	private $events;
	/**
	 * Site-bound options table.
	 *
	 * @var string
	 */
	private $table;
	/**
	 * WordPress blog at construction.
	 *
	 * @var int
	 */
	private $blog_id;

	/**
	 * Use the site's unique option-name key without autoloading payment bindings.
	 *
	 * @param \wpdb                  $db Database connection.
	 * @param PaymentEventRepository $events Receipt storage bound to the same site.
	 */
	public function __construct( \wpdb $db, PaymentEventRepository $events ) {
		$this->db      = $db;
		$this->events  = $events;
		$this->table   = $db->options;
		$this->blog_id = get_current_blog_id();
	}

	/**
	 * Bind a published receipt without replacing any prior charge owner.
	 *
	 * @param array  $scope Exact historical intent scope.
	 * @param string $revision Published receipt revision.
	 * @return array Bound, conflict, or explicit storage/evidence failure.
	 */
	public function bind( array $scope, string $revision ): array {
		if ( get_current_blog_id() !== $this->blog_id ) {
			return [ 'state' => 'invalid' ];
		}
		$receipt = $this->events->read_revision( $scope, $revision );
		if ( 'found' !== $receipt['state'] ) {
			return $receipt;
		}
		$charge = $receipt['event']['charge_id'] ?? null;
		if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) || ! is_string( $charge ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) ) {
			return [ 'state' => 'invalid' ];
		}
		$key = $this->key( $scope['account_id'], $scope['site_id'], $scope['test_mode'], $charge );
		if ( null === $key ) {
			return [ 'state' => 'invalid' ];
		}
		ksort( $scope );
		$payload = wp_json_encode(
			[
				'scope'            => $scope,
				'receipt_revision' => $revision,
			]
		);
		if ( false === $payload || strlen( $payload ) > 4096 ) {
			return [ 'state' => 'invalid' ];
		}
		// WordPress add_option can upsert; a binding must never replace a competing owner.
		$this->db->query( $this->db->prepare( "INSERT IGNORE INTO {$this->table} (option_name,option_value,autoload) VALUES (%s,%s,'no')", $key, $payload ) );
		$saved = $this->resolve( $scope['account_id'], $scope['site_id'], $scope['test_mode'], $charge );
		if ( 'found' !== $saved['state'] ) {
			return 'missing' === $saved['state'] ? [ 'state' => 'unavailable' ] : $saved;
		}
		if ( $saved['scope'] !== $scope ) {
			return [ 'state' => 'conflict' ];
		}
		// One immutable reference per receipt avoids an order-wide read/modify/write race.
		$order_key = 'wcpay_order_receipt_' . $scope['order_id'] . '_' . hash( 'sha256', wp_json_encode( $scope ) );
		$locator   = wp_json_encode(
			[
				'scope'            => $scope,
				'receipt_revision' => $saved['receipt_revision'],
			]
		);
		$this->db->query( $this->db->prepare( "INSERT IGNORE INTO {$this->table} (option_name,option_value,autoload) VALUES (%s,%s,'no')", $order_key, $locator ) );
		$stored = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->table} WHERE option_name=%s", $order_key ) );
		return [ 'state' => ! $this->db->last_error && $stored === $locator ? 'bound' : 'unavailable' ];
	}

	/**
	 * Resolve historical identity without reading current account or order metadata.
	 *
	 * @param string $account Historical connected account.
	 * @param int    $site Historical connected site ID.
	 * @param bool   $test_mode Historical payment mode.
	 * @param string $charge Charge identifier.
	 * @return array Verified receipt locator or explicit failure.
	 */
	public function resolve( string $account, int $site, bool $test_mode, string $charge ): array {
		$key = $this->key( $account, $site, $test_mode, $charge );
		if ( null === $key || get_current_blog_id() !== $this->blog_id ) {
			return [ 'state' => 'invalid' ];
		}
		$payload = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->table} WHERE option_name=%s", $key ) );
		if ( $this->db->last_error ) {
			return [ 'state' => 'unavailable' ];
		}
		if ( null === $payload ) {
			return [ 'state' => 'missing' ];
		}
		$data  = strlen( $payload ) <= 4096 ? json_decode( $payload, true ) : null;
		$scope = $data['scope'] ?? null;
		if ( ! is_array( $scope ) || ! is_string( $data['receipt_revision'] ?? null ) || ( $scope['account_id'] ?? null ) !== $account || ( $scope['site_id'] ?? null ) !== $site || ( $scope['test_mode'] ?? null ) !== $test_mode || ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) ) {
			return [ 'state' => 'invalid' ];
		}
		$receipt = $this->events->read_revision( $scope, $data['receipt_revision'] );
		if ( 'found' !== $receipt['state'] ) {
			return $receipt;
		}
		if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) || ( $receipt['event']['charge_id'] ?? null ) !== $charge ) {
			return [ 'state' => 'invalid' ];
		}
		ksort( $scope );
		return [
			'state'            => 'found',
			'scope'            => $scope,
			'receipt_revision' => $data['receipt_revision'],
		];
	}

	/**
	 * Locate a bounded page of known receipts, never complete payment membership.
	 *
	 * The site is bound by the constructor's options table. Historical connected
	 * account, site and mode are recovered from each verified receipt reference.
	 * A missing page means unknown evidence, not a zero payment total. Pagination
	 * is a discovery cursor, not an atomic snapshot across concurrent inserts.
	 *
	 * @param int    $order_id Canonical order ID.
	 * @param int    $limit Maximum references to return (1–100).
	 * @param string $after Last returned scope hash, or empty for the first page.
	 * @return array Known references with pagination, unknown, or explicit failure.
	 */
	public function find_for_order( int $order_id, int $limit = 50, string $after = '' ): array {
		if ( get_current_blog_id() !== $this->blog_id || $order_id <= 0 || $limit < 1 || $limit > 100 || ( '' !== $after && ! preg_match( '/^[a-f0-9]{64}$/D', $after ) ) ) {
			return [ 'state' => 'invalid' ];
		}
		$prefix = 'wcpay_order_receipt_' . $order_id . '_';
		$rows   = $this->db->get_results(
			$this->db->prepare(
				"SELECT option_name,option_value FROM {$this->table} WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name ASC LIMIT %d",
				$this->db->esc_like( $prefix ) . '%',
				$prefix . $after,
				$limit + 1
			),
			ARRAY_A
		);
		if ( $this->db->last_error || ! is_array( $rows ) ) {
			return [ 'state' => 'unavailable' ];
		}
		$has_more = count( $rows ) > $limit;
		$receipts = [];
		$cursor   = $after;
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$data  = strlen( $row['option_value'] ) <= 4096 ? json_decode( $row['option_value'], true ) : null;
			$scope = $data['scope'] ?? null;
			if ( ! is_array( $scope ) || ( $scope['order_id'] ?? null ) !== $order_id || ! is_string( $data['receipt_revision'] ?? null ) ) {
				return [ 'state' => 'invalid' ];
			}
			ksort( $scope );
			$hash = hash( 'sha256', wp_json_encode( $scope ) );
			if ( $row['option_name'] !== $prefix . $hash ) {
				return [ 'state' => 'invalid' ];
			}
			$receipt = $this->events->read_revision( $scope, $data['receipt_revision'] );
			if ( 'found' !== $receipt['state'] ) {
				return $receipt;
			}
			$charge = $receipt['event']['charge_id'] ?? null;
			if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) || ! is_string( $charge ) ) {
				return [ 'state' => 'invalid' ];
			}
			$binding = $this->resolve( $scope['account_id'], $scope['site_id'], $scope['test_mode'], $charge );
			if ( 'found' !== $binding['state'] || $binding['scope'] !== $scope || $binding['receipt_revision'] !== $data['receipt_revision'] ) {
				return [ 'state' => 'unavailable' ];
			}
			$receipts[] = [
				'scope'            => $scope,
				'receipt_revision' => $data['receipt_revision'],
			];
			$cursor     = $hash;
		}
		return [
			'state'       => $receipts ? 'known' : 'unknown',
			'receipts'    => $receipts,
			'has_more'    => $has_more,
			'next_cursor' => $cursor,
		];
	}

	/**
	 * Canonical account/site/mode/charge lookup key.
	 *
	 * @param string $account Account ID.
	 * @param int    $site Site ID.
	 * @param bool   $test_mode Mode.
	 * @param string $charge Charge ID.
	 * @return string|null Private option key or invalid identity.
	 */
	private function key( string $account, int $site, bool $test_mode, string $charge ): ?string {
		if ( $site <= 0 || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $account ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $charge ) ) {
			return null;
		}
		return 'wcpay_receipt_index_' . hash( 'sha256', wp_json_encode( [ $account, $site, $test_mode, $charge ] ) );
	}
}
