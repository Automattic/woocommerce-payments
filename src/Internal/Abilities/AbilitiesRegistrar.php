<?php
/**
 * Class AbilitiesRegistrar
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooPayments with the WordPress Abilities API.
 *
 * Declares the `woocommerce-payments` ability category and registers 15
 * read-only abilities covering account state, transactions (list + summary),
 * disputes (list + summary + single), authorizations (list + summary),
 * payouts/deposits (list + overview + summary), single intent/charge/timeline
 * lookups, and the active Stripe Capital loan summary. All abilities gate on
 * `manage_woocommerce`, matching `WC_Payments_REST_Controller::check_permission()`.
 *
 * Registration is gated behind the `woocommerce_payments_abilities_enabled`
 * filter (default `false`) so the scaffolding can ship without committing
 * the final surface; per-site opt-in until validated.
 *
 * Implemented as a static class rather than a DI-wired instance (the
 * convention for hook-registering classes elsewhere under `src/Internal/`)
 * because the Abilities API registers callables at action hook time;
 * static methods serialize cleanly across `add_action()` boundaries without
 * needing the DI container at dispatch time.
 *
 * Delegation errors and init-failure conversions are logged to the
 * `woopayments-abilities` source via `wc_get_logger()`.
 */
class AbilitiesRegistrar {

	/**
	 * The category slug used for every WooPayments ability.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woocommerce-payments';

	/**
	 * Translate from the agent-facing pagination/sort key names to the
	 * names the WooPayments `Paginated` request class expects.
	 *
	 * The ability `input_schema` uses WP-Core REST conventions (`per_page`,
	 * `orderby`, `order`) because that is what MCP clients and the Abilities
	 * REST bridge expect. The backing `Paginated::from_rest_request()`
	 * (`includes/core/server/request/class-paginated.php`) reads `pagesize`,
	 * `sort`, and `direction`. Without translation, list abilities silently
	 * return the default 25 rows / created-desc regardless of what the
	 * caller asked for.
	 *
	 * `page` is named the same in both layers and passes through unchanged.
	 *
	 * @var array<string,string>
	 */
	const PAGINATION_KEY_MAP = [
		'per_page' => 'pagesize',
		'orderby'  => 'sort',
		'order'    => 'direction',
	];

	/**
	 * Ability definition classes registered through the WC 10.9 loader.
	 *
	 * Filled in incrementally as each ability migrates from inline
	 * register_*() / execute_*() to a Domain class. Once every ability
	 * lives in this list, the inline register_abilities() aggregator
	 * is removed.
	 *
	 * @var array<int, class-string>
	 */
	private const ABILITY_CLASSES = [
		\WCPay\Internal\Abilities\Domain\GetAccount::class,
		\WCPay\Internal\Abilities\Domain\GetDepositsOverview::class,
		\WCPay\Internal\Abilities\Domain\GetActiveLoanSummary::class,
		\WCPay\Internal\Abilities\Domain\GetTransactionsSummary::class,
		\WCPay\Internal\Abilities\Domain\GetDisputesSummary::class,
		\WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary::class,
		\WCPay\Internal\Abilities\Domain\GetDepositsSummary::class,
		\WCPay\Internal\Abilities\Domain\GetDispute::class,
		\WCPay\Internal\Abilities\Domain\GetPaymentIntent::class,
		\WCPay\Internal\Abilities\Domain\GetCharge::class,
		\WCPay\Internal\Abilities\Domain\GetTimeline::class,
		\WCPay\Internal\Abilities\Domain\GetTransactions::class,
	];

	/**
	 * Tracks whether the category has been registered in this process to
	 * keep `register_category()` idempotent across action variants.
	 *
	 * @var bool
	 */
	private static $category_registered = false;

	/**
	 * Tracks whether the ability set has been registered in this process to
	 * keep `register_abilities()` idempotent across action variants.
	 *
	 * @var bool
	 */
	private static $abilities_registered = false;

	/**
	 * Initialize the abilities registration.
	 *
	 * Gated behind the `woocommerce_payments_abilities_enabled` filter (default
	 * false during rollout). Flip via `add_filter()` on a per-site basis to
	 * enable; default to `true` once the feature graduates.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filter whether WooPayments' Abilities API registrations are active.
		 *
		 * Default false during initial rollout so the scaffolding can ship
		 * without committing to the final ability shape. Flip per-site to
		 * opt in. The filter name uses the full `woocommerce_payments_`
		 * prefix (rather than the internal `wcpay_` prefix used by most
		 * filters in this plugin) because the name is part of the public
		 * surface that MCP clients and external documentation tools will
		 * key off of — matching the plugin slug is the more discoverable
		 * choice for an externally-facing toggle.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Whether to register WooPayments abilities. Default false.
		 * @return bool
		 */
		if ( ! apply_filters( 'woocommerce_payments_abilities_enabled', false ) ) {
			return;
		}

		// WC 10.9 loader-driven registration path. On WC < 10.9 this
		// short-circuits; the legacy add_action() path below keeps the
		// partially-migrated state functional. Once every ability has
		// moved to a Domain class, the legacy path goes away.
		if ( self::woo_abilities_loader_available() ) {
			add_filter( 'woocommerce_ability_definition_classes', [ __CLASS__, 'append_classes' ] );
		}

		// The abilities-api composer package fires the non-prefixed action
		// names; WP Core's merge target uses the `wp_` variants. Hook both
		// so the registrar works against either version of the API. The
		// idempotency guards in register_category() / register_abilities()
		// handle the dual-hook double-fire scenario.
		add_action( 'abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Append WCPay ability definition classes to Woo Core's loader.
	 *
	 * Filter callback for `woocommerce_ability_definition_classes`. The
	 * loader iterates the resulting class list, calls
	 * `is_a( $class, AbilityDefinition::class, true )` on each, and
	 * registers those that pass via wp_register_ability().
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
	}

	/**
	 * Register the WooPayments ability category.
	 *
	 * Idempotent — re-registering the same category, or looking it up before
	 * it is registered, both fire `_doing_it_wrong` from the Abilities API.
	 * Tracking with a static flag keeps both paths silent across the variant
	 * action names (`abilities_api_categories_init` and the WP-Core merge
	 * target `wp_abilities_api_categories_init`).
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( self::$category_registered ) {
			return;
		}

		self::$category_registered = true;

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				// "WooPayments" is a product name and is not translated.
				'label'       => 'WooPayments',
				'description' => __( 'Abilities for inspecting a merchant\'s WooPayments account, transactions, disputes, payouts, and related state.', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Register all WooPayments abilities.
	 *
	 * Idempotent — guarded by a static flag so the variant action names
	 * (`abilities_api_init` and the WP-Core merge target `wp_abilities_api_init`)
	 * don't double-register if both fire in the same process.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( self::$abilities_registered ) {
			return;
		}

		self::$abilities_registered = true;

		self::register_get_disputes_ability();
		self::register_get_authorizations_ability();
		self::register_get_deposits_ability();
	}

	/**
	 * Permission callback mirroring WC_Payments_REST_Controller::check_permission().
	 *
	 * Used as the `permission_callback` for every WooPayments ability so the
	 * authorization surface matches the existing admin REST API.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_woocommerce() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Reset registrar state for tests.
	 *
	 * Resets the static idempotency flags so a single PHPUnit process can
	 * exercise registration paths repeatedly without each test inheriting the
	 * "already registered" state from a prior one. Tests that want to verify
	 * `register_*` runs correctly should call this in `tear_down()`.
	 *
	 * Note: the upstream `WP_Abilities_Registry` / `WP_Ability_Categories_Registry`
	 * singletons retain abilities and categories registered earlier in the
	 * process — those are not reset here because clearing them would wipe
	 * WooCommerce Core's registrations alongside ours. Tests that need a
	 * pristine registry should run in a dedicated process / `@runInSeparateProcess`.
	 *
	 * @internal
	 *
	 * @return void
	 * @throws \Exception When called outside the PHPUnit test environment.
	 */
	public static function reset_for_testing() {
		if ( ! defined( 'WCPAY_TEST_ENV' ) ) {
			throw new \Exception( 'AbilitiesRegistrar::reset_for_testing() must not be called outside the PHPUnit test environment.' );
		}
		self::$category_registered  = false;
		self::$abilities_registered = false;
	}

	/**
	 * Execute callback — `get-disputes`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_disputes( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/disputes', is_array( $input ) ? $input : [] );
	}

	/**
	 * Execute callback — `get-authorizations`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_authorizations( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/authorizations', is_array( $input ) ? $input : [] );
	}

	/**
	 * Execute callback — `get-deposits`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_deposits( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/deposits', is_array( $input ) ? $input : [] );
	}

	/**
	 * Delegate to a REST route via `rest_do_request()`.
	 *
	 * Translates pagination/sort keys at the boundary (see `PAGINATION_KEY_MAP`),
	 * builds a WP_REST_Request, dispatches through the REST router, and unwraps
	 * the response. Permissions are enforced by the backing route's
	 * `permission_callback`; caching is the backing controller's concern.
	 *
	 * @param string              $http_method HTTP method (GET, POST, …).
	 * @param string              $route       REST route path (e.g. `/wc/v3/payments/transactions`).
	 * @param array<string,mixed> $params      Request parameters (query/body).
	 * @return array|\WP_Error Unwrapped response data, or WP_Error on failure.
	 */
	public static function delegate_to_rest_controller( $http_method, $route, $params = [] ) {
		// Translate WP-Core REST pagination/sort keys to the names the
		// WooPayments Paginated request class consumes. When the caller
		// supplies both the agent-facing key (e.g. `per_page`) and the
		// canonical key (e.g. `pagesize`), the canonical key wins — silently
		// overwriting an explicit `pagesize` was a footgun in earlier drafts.
		foreach ( self::PAGINATION_KEY_MAP as $agent_key => $request_key ) {
			if ( array_key_exists( $agent_key, $params ) ) {
				if ( ! array_key_exists( $request_key, $params ) ) {
					$params[ $request_key ] = $params[ $agent_key ];
				}
				unset( $params[ $agent_key ] );
			}
		}

		$request = new \WP_REST_Request( $http_method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response instanceof \WP_REST_Response && $response->is_error() ) {
			$response = $response->as_error();
		}

		if ( is_wp_error( $response ) ) {
			wc_get_logger()->error(
				sprintf(
					'AbilitiesRegistrar delegation failed [%s %s]: %s (%s)',
					$http_method,
					$route,
					$response->get_error_message(),
					$response->get_error_code()
				),
				[ 'source' => 'woopayments-abilities' ]
			);
			return $response;
		}

		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}

		// @codeCoverageIgnoreStart -- rest_do_request() always returns WP_Error or WP_REST_Response in practice; this raw-array fallback is defensive.
		return is_array( $response ) ? $response : [];
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Whether WooCommerce 10.9's AbilitiesLoader is available.
	 *
	 * Used as a hard gate for the WC 10.9 filter-driven registration path.
	 * On WC < 10.9 the filter wire is skipped; the legacy direct-registration
	 * path (action hooks on `abilities_api_init` / `wp_abilities_api_init`)
	 * still handles the abilities that have not yet migrated to Domain
	 * classes.
	 *
	 * WC 10.9 also depends on WP 6.9, so wp_register_ability() is implicitly
	 * available wherever the loader exists.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}

	/**
	 * Register the `woocommerce-payments/get-disputes` ability.
	 *
	 * @return void
	 */
	private static function register_get_disputes_ability() {
		wp_register_ability(
			'woocommerce-payments/get-disputes',
			[
				'label'               => __( 'List disputes', 'woocommerce-payments' ),
				'description'         => __( 'List disputes with status and date-range filters. Answers \'which disputes need response?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::disputes_list_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_disputes' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-authorizations` ability.
	 *
	 * @return void
	 */
	private static function register_get_authorizations_ability() {
		wp_register_ability(
			'woocommerce-payments/get-authorizations',
			[
				'label'               => __( 'List authorizations', 'woocommerce-payments' ),
				'description'         => __( 'List uncaptured card authorizations. Answers \'which authorizations expire soon / still need capture?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::pagination_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_authorizations' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-deposits` ability.
	 *
	 * @return void
	 */
	private static function register_get_deposits_ability() {
		wp_register_ability(
			'woocommerce-payments/get-deposits',
			[
				'label'               => __( 'List payouts', 'woocommerce-payments' ),
				'description'         => __( 'List payouts (Stripe deposits) with status, date-range, and currency filters. Answers \'show me my recent payouts\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::deposits_list_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_deposits' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Standard `read` meta used by every read ability registered here.
	 *
	 * @return array
	 */
	private static function read_meta() {
		return [
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'show_in_rest' => true,
			// `mcp.public` is read by the wordpress/mcp-adapter package
			// (McpAbilityHelperTrait::is_public_mcp_ability() +
			// DefaultServerFactory::register_abilities()) to gate MCP
			// discoverability. The key is the adapter's contract, not a
			// WP Core convention.
			'mcp'          => [
				'public' => true,
			],
		];
	}

	/**
	 * Empty input schema used by zero-arg abilities.
	 *
	 * @return array
	 */
	private static function zero_arg_input_schema() {
		return [
			'type'                 => 'object',
			'default'              => (object) [],
			'properties'           => [],
			'additionalProperties' => false,
		];
	}

	/**
	 * Input schema for transactions list/summary abilities. Accepts the common
	 * filters surfaced by the backing controller. `additionalProperties: true`
	 * because the underlying List_Transactions request supports many more
	 * filters than are useful to document inline; the controller validates.
	 *
	 * @return array
	 */
	private static function transactions_list_input_schema() {
		return [
			'type'                 => 'object',
			'default'              => (object) [],
			'properties'           => [
				'match'                => [
					'type'        => 'string',
					'description' => 'Filter join mode (any|all).',
				],
				'date_before'          => [
					'type'        => 'string',
					'description' => 'ISO-8601 date upper bound.',
				],
				'date_after'           => [
					'type'        => 'string',
					'description' => 'ISO-8601 date lower bound.',
				],
				'date_between'         => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Two-element ISO date range [start, end].',
				],
				'type_is'              => [
					'type'        => 'string',
					'description' => 'Transaction type filter (charge|refund|dispute|adjustment|…).',
				],
				'source_device_is'     => [ 'type' => 'string' ],
				'channel_is'           => [ 'type' => 'string' ],
				'customer_country_is'  => [ 'type' => 'string' ],
				'risk_level_is'        => [ 'type' => 'string' ],
				'store_currency_is'    => [ 'type' => 'string' ],
				'customer_currency_is' => [ 'type' => 'string' ],
				'search'               => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'page'                 => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'per_page'             => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				],
				'orderby'              => [ 'type' => 'string' ],
				'order'                => [
					'type' => 'string',
					'enum' => [ 'asc', 'desc' ],
				],
				'deposit_id'           => [
					'type'        => 'string',
					'description' => 'Filter to transactions belonging to a single payout (deposit) ID. Accepted by both the list and the summary endpoints.',
				],
			],
			'additionalProperties' => true,
		];
	}

	/**
	 * Input schema for disputes list/summary abilities.
	 *
	 * @return array
	 */
	private static function disputes_list_input_schema() {
		return [
			'type'                 => 'object',
			'default'              => (object) [],
			'properties'           => [
				'match'             => [ 'type' => 'string' ],
				'store_currency_is' => [ 'type' => 'string' ],
				'date_before'       => [ 'type' => 'string' ],
				'date_after'        => [ 'type' => 'string' ],
				'date_between'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'search'            => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'status_is'         => [
					'type'        => 'string',
					'description' => 'Dispute status (warning_needs_response|needs_response|under_review|won|lost|warning_under_review|warning_closed).',
				],
				'status_is_not'     => [ 'type' => 'string' ],
				'page'              => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'per_page'          => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				],
				'orderby'           => [ 'type' => 'string' ],
				'order'             => [
					'type' => 'string',
					'enum' => [ 'asc', 'desc' ],
				],
			],
			'additionalProperties' => true,
		];
	}

	/**
	 * Input schema for deposits (payouts) list/summary abilities.
	 *
	 * @return array
	 */
	private static function deposits_list_input_schema() {
		return [
			'type'                 => 'object',
			'default'              => (object) [],
			'properties'           => [
				'match'             => [ 'type' => 'string' ],
				'store_currency_is' => [ 'type' => 'string' ],
				'date_before'       => [ 'type' => 'string' ],
				'date_after'        => [ 'type' => 'string' ],
				'date_between'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'status_is'         => [
					'type'        => 'string',
					'description' => 'Payout status (paid|pending|in_transit|canceled|failed).',
				],
				'status_is_not'     => [ 'type' => 'string' ],
				'page'              => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'per_page'          => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				],
				'orderby'           => [ 'type' => 'string' ],
				'order'             => [
					'type' => 'string',
					'enum' => [ 'asc', 'desc' ],
				],
			],
			'additionalProperties' => true,
		];
	}

	/**
	 * Generic pagination-only input schema for endpoints that take no other filters.
	 *
	 * @return array
	 */
	private static function pagination_input_schema() {
		return [
			'type'                 => 'object',
			'default'              => (object) [],
			'properties'           => [
				'page'     => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'per_page' => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				],
				'orderby'  => [ 'type' => 'string' ],
				'order'    => [
					'type' => 'string',
					'enum' => [ 'asc', 'desc' ],
				],
			],
			// Closed contract — the only consumer of this schema is the
			// `get-authorizations` ability, whose backing
			// `List_Authorizations` extends `Paginated` with no extra
			// filters. Any extra key would silently no-op at the request
			// layer, so we reject them up front.
			'additionalProperties' => false,
		];
	}

	/**
	 * Strip pagination and sort properties from a list-style input schema so
	 * it can be reused for the matching summary ability. Summary endpoints
	 * don't paginate or sort — keeping the keys in the schema is misleading
	 * because they would silently no-op.
	 *
	 * @param array $list_schema Input schema produced by a `*_list_input_schema()` helper.
	 * @return array
	 */
	private static function filters_only( array $list_schema ): array {
		foreach ( [ 'page', 'per_page', 'orderby', 'order' ] as $key ) {
			unset( $list_schema['properties'][ $key ] );
		}
		return $list_schema;
	}
}
