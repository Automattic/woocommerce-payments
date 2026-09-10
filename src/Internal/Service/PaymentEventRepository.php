<?php
/**
 * Immutable payment evidence revisions with conditional publication.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Storage only; callers must qualify monetary evidence before reporting it. */
class PaymentEventRepository {
	/**
	 * Database connection.
	 *
	 * @var \wpdb
	 */
	private $db;
	/**
	 * Revision table.
	 *
	 * @var string
	 */
	private $revisions;
	/**
	 * Head table.
	 *
	 * @var string
	 */
	private $heads;
	/**
	 * Memoized schema verdict for this request.
	 *
	 * @var bool|null
	 */
	private $schema_compatible = null;

	/**
	 * Construct without creating tables or scheduling work.
	 *
	 * @param \wpdb $db Database connection.
	 */
	public function __construct( \wpdb $db ) {
		$this->db        = $db;
		$this->revisions = $db->prefix . 'wcpay_event_revisions';
		$this->heads     = $db->prefix . 'wcpay_event_heads';
	}

	/**
	 * Create the initial schema for the independently retried startup installer.
	 *
	 * @return bool Whether both tables exist with the required storage contract.
	 */
	public function create_schema(): bool {
		$revisions               = $this->db->query( "CREATE TABLE IF NOT EXISTS {$this->revisions} (partition_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, revision char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, payload longtext NOT NULL, PRIMARY KEY (partition_key, revision)) ENGINE=InnoDB" );
		$heads                   = $this->db->query( "CREATE TABLE IF NOT EXISTS {$this->heads} (partition_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY, revision varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL) ENGINE=InnoDB" );
		$this->schema_compatible = null;
		return false !== $revisions && false !== $heads && $this->is_schema_compatible();
	}

	/**
	 * Verify the schema before using its uniqueness and comparison guarantees.
	 *
	 * @return bool Whether both tables have the required engine, columns and keys.
	 */
	public function is_schema_compatible(): bool {
		// Six metadata queries per call, and one customer-history render makes this
		// call dozens of times per order. The schema cannot change under a request
		// except through create_schema above, which clears this.
		if ( null !== $this->schema_compatible ) {
			return $this->schema_compatible;
		}
		$this->schema_compatible = $this->table_is_compatible(
			$this->revisions,
			[
				'partition_key' => 'char(64)',
				'revision'      => 'char(64)',
				'payload'       => 'longtext',
			],
			[ 'partition_key', 'revision' ]
		)
			&& $this->table_is_compatible(
				$this->heads,
				[
					'partition_key' => 'char(64)',
					'revision'      => 'varchar(64)',
				],
				[ 'partition_key' ]
			);
		return $this->schema_compatible;
	}

	/**
	 * Publish against the exact previously observed head; never retry a lost race.
	 *
	 * @param array  $scope Account/site/mode/order/event identity.
	 * @param string $expected_revision Previous head, or empty for the first revision.
	 * @param array  $event Evidence payload; this method does not qualify its amounts.
	 * @return array Publication state and revision identifier.
	 */
	public function publish( array $scope, string $expected_revision, array $event ): array {
		$scope = $this->normalize_scope( $scope );
		if ( null === $scope || ! $this->valid_revision( $expected_revision ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( ! $this->is_schema_compatible() ) {
			return [ 'state' => 'unavailable' ];
		}
		$partition = hash( 'sha256', wp_json_encode( $scope ) );
		$payload   = wp_json_encode(
			[
				'version' => 1,
				'scope'   => $scope,
				'parent'  => $expected_revision,
				'event'   => $event,
			]
		);
		if ( false === $payload || strlen( $payload ) > 1048576 ) {
			return [ 'state' => 'invalid' ];
		}
		$revision = hash( 'sha256', $payload );
		// An orphan is harmless to readers; its observation is retained for reconciliation.
		$this->db->query( $this->db->prepare( "INSERT IGNORE INTO {$this->revisions} (partition_key,revision,payload) VALUES (%s,%s,%s)", $partition, $revision, $payload ) );
		$saved = $this->db->get_var( $this->db->prepare( "SELECT payload FROM {$this->revisions} WHERE partition_key=%s AND revision=%s", $partition, $revision ) );
		if ( $saved !== $payload ) {
			return [
				'state'    => 'unavailable',
				'revision' => $revision,
			];
		}
		// Initialization cannot replace an existing head.
		$this->db->query( $this->db->prepare( "INSERT IGNORE INTO {$this->heads} (partition_key,revision) VALUES (%s,'')", $partition ) );
		$before = $this->walk( $scope, $partition, $revision );
		if ( 'found' !== $before['state'] ) {
			return 'missing' === $before['state'] ? [ 'state' => 'unavailable' ] : $before;
		}
		if ( $before['contains'] ) {
			return [
				'state'    => 'published',
				'revision' => $revision,
			];
		}
		if ( $before['revision'] !== $expected_revision ) {
			return [
				'state'    => 'conflict',
				'revision' => $revision,
			];
		}
		if ( $before['depth'] >= 1000 ) {
			return [
				'state'    => 'history_limit',
				'revision' => $revision,
			];
		}
		$this->db->query( $this->db->prepare( "UPDATE {$this->heads} SET revision=%s WHERE partition_key=%s AND revision=%s", $revision, $partition, $expected_revision ) );
		// Read back even after an error: a lost acknowledgement may follow a commit.
		$after = $this->walk( $scope, $partition, $revision );
		if ( 'found' !== $after['state'] ) {
			return $after;
		}
		return [
			'state'    => $after['contains'] ? 'published' : ( $after['revision'] === $expected_revision ? 'unavailable' : 'conflict' ),
			'revision' => $revision,
		];
	}

	/**
	 * Read and validate the reachable chain without fetching provider data.
	 *
	 * @param array $scope Account/site/mode/order/event identity.
	 * @return array Found evidence, missing data, or unavailable/invalid history.
	 */
	public function read( array $scope ): array {
		$scope = $this->normalize_scope( $scope );
		if ( null === $scope ) {
			return [ 'state' => 'invalid' ];
		}
		if ( ! $this->is_schema_compatible() ) {
			return [ 'state' => 'unavailable' ];
		}
		$result = $this->walk( $scope, hash( 'sha256', wp_json_encode( $scope ) ), '' );
		unset( $result['contains'], $result['depth'], $result['target_event'] );
		return $result;
	}

	/**
	 * Resolve an exact published dependency for a historical report.
	 *
	 * Orphaned observations are not published evidence. The returned head is only
	 * the head observed during this read; it does not certify report freshness.
	 *
	 * @param array  $scope Account/site/mode/order/event identity.
	 * @param string $revision Required immutable revision hash.
	 * @return array Exact evidence and observed head, or an explicit failure state.
	 */
	public function read_revision( array $scope, string $revision ): array {
		$scope = $this->normalize_scope( $scope );
		if ( null === $scope || '' === $revision || ! $this->valid_revision( $revision ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( ! $this->is_schema_compatible() ) {
			return [ 'state' => 'unavailable' ];
		}
		$result = $this->walk( $scope, hash( 'sha256', wp_json_encode( $scope ) ), $revision );
		if ( 'found' !== $result['state'] ) {
			return $result;
		}
		if ( ! $result['contains'] ) {
			return [ 'state' => 'missing' ];
		}
		return [
			'state'         => 'found',
			'revision'      => $revision,
			'head_revision' => $result['revision'],
			'event'         => $result['target_event'],
		];
	}

	/**
	 * Refuse incompatible existing tables without altering their data.
	 *
	 * @param string $table Trusted constructor-derived table name.
	 * @param array  $expected_columns Required column names and SQL types.
	 * @param array  $expected_primary Required primary key column order.
	 * @return bool Whether the table provides the required storage guarantees.
	 */
	private function table_is_compatible( string $table, array $expected_columns, array $expected_primary ): bool {
		$status = $this->db->get_row( $this->db->prepare( 'SHOW TABLE STATUS LIKE %s', $this->db->esc_like( $table ) ), ARRAY_A );
		if ( ! is_array( $status ) || 'INNODB' !== strtoupper( $status['Engine'] ?? '' ) ) {
			return false;
		}
		$columns = $this->db->get_results( "SHOW FULL COLUMNS FROM {$table}", ARRAY_A );
		if ( ! is_array( $columns ) || count( $columns ) !== count( $expected_columns ) ) {
			return false;
		}
		foreach ( $columns as $column ) {
			$name = $column['Field'];
			if ( ! isset( $expected_columns[ $name ] ) || $column['Type'] !== $expected_columns[ $name ] || 'NO' !== $column['Null'] || ( 'payload' !== $name && 'ascii_bin' !== $column['Collation'] ) || '' !== $column['Extra'] ) {
				return false;
			}
		}
		$indexes = $this->db->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
		if ( ! is_array( $indexes ) ) {
			return false;
		}
		$primary = [];
		foreach ( $indexes as $index ) {
			if ( 'PRIMARY' === $index['Key_name'] ) {
				if ( 0 !== (int) $index['Non_unique'] || null !== $index['Sub_part'] || 'BTREE' !== $index['Index_type'] ) {
					return false;
				}
				$primary[ (int) $index['Seq_in_index'] ] = $index['Column_name'];
			} elseif ( 0 === (int) $index['Non_unique'] ) {
				return false;
			}
		}
		ksort( $primary );
		return array_values( $primary ) === $expected_primary;
	}

	/**
	 * Canonicalize a fully specified event partition.
	 *
	 * @param array $scope Supplied identity.
	 * @return array|null Normalized identity, or null when invalid.
	 */
	private function normalize_scope( array $scope ): ?array {
		if ( 5 !== count( $scope ) || ! is_string( $scope['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $scope['account_id'] ) || ! is_int( $scope['site_id'] ?? null ) || $scope['site_id'] <= 0 || ! is_int( $scope['order_id'] ?? null ) || $scope['order_id'] <= 0 || ! is_bool( $scope['test_mode'] ?? null ) || ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^[a-z]+_[a-zA-Z0-9]+$/D', $scope['event_id'] ) ) {
			return null;
		}
		ksort( $scope );
		return $scope;
	}

	/**
	 * Validate a pointer including the initial empty value.
	 *
	 * @param string $revision Pointer value.
	 * @return bool Whether well formed.
	 */
	private function valid_revision( string $revision ): bool {
		return '' === $revision || 1 === preg_match( '/^[a-f0-9]{64}$/D', $revision );
	}

	/**
	 * Validate every reachable revision and resolve retries of already published ancestors.
	 *
	 * @param array  $scope Normalized partition identity.
	 * @param string $partition Partition key.
	 * @param string $target Candidate revision to find.
	 * @return array Validated head and ancestor membership, or an explicit failure.
	 */
	private function walk( array $scope, string $partition, string $target ): array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT revision FROM {$this->heads} WHERE partition_key=%s", $partition ), ARRAY_A );
		if ( $this->db->last_error ) {
			return [ 'state' => 'unavailable' ];
		}
		if ( null === $row ) {
			return [ 'state' => 'missing' ];
		}
		$head = $row['revision'];
		if ( ! $this->valid_revision( $head ) ) {
			return [ 'state' => 'invalid' ];
		}
		$cursor       = $head;
		$contains     = false;
		$event        = null;
		$target_event = null;
		for ( $depth = 0; '' !== $cursor && $depth < 1000; ++$depth ) {
			$payload = $this->db->get_var( $this->db->prepare( "SELECT payload FROM {$this->revisions} WHERE partition_key=%s AND revision=%s", $partition, $cursor ) );
			if ( $this->db->last_error ) {
				return [ 'state' => 'unavailable' ];
			}
			$data = is_string( $payload ) ? json_decode( $payload, true ) : null;
			if ( ! is_array( $data ) || hash( 'sha256', $payload ) !== $cursor || 1 !== ( $data['version'] ?? null ) || ( $data['scope'] ?? null ) !== $scope || ! is_array( $data['event'] ?? null ) || ! is_string( $data['parent'] ?? null ) || ! $this->valid_revision( $data['parent'] ) ) {
				return [ 'state' => 'invalid' ];
			}
			if ( 0 === $depth ) {
				$event = $data['event'];
			}
			$contains = $contains || $cursor === $target;
			if ( $cursor === $target ) {
				$target_event = $data['event'];
			}
			$cursor = $data['parent'];
		}
		if ( '' !== $cursor ) {
			return [ 'state' => 'history_limit' ];
		}
		return [
			'state'        => 'found',
			'revision'     => $head,
			'event'        => $event,
			'contains'     => $contains,
			'depth'        => $depth,
			'target_event' => $target_event,
		];
	}
}
