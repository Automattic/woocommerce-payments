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

		// The abilities-api package fires the non-prefixed action names
		// (`abilities_api_init`, `abilities_api_categories_init`). WooCommerce
		// Core additionally hooks the `wp_` variants for forward compatibility
		// with any future WP-Core-merged naming; we mirror that. The
		// idempotency guards in `register_category()` / per-ability helpers
		// make repeated invocations safe if both action names fire.
		if ( did_action( 'abilities_api_categories_init' ) || did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
			add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		}

		if ( did_action( 'abilities_api_init' ) || did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'abilities_api_init', [ __CLASS__, 'register_abilities' ] );
			add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		}
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
		if ( self::$category_registered || ! function_exists( 'wp_register_ability_category' ) ) {
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
		if ( self::$abilities_registered || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::$abilities_registered = true;

		self::register_get_account_ability();
		self::register_get_deposits_overview_ability();
		self::register_get_transactions_ability();
		self::register_get_transactions_summary_ability();
		self::register_get_disputes_ability();
		self::register_get_disputes_summary_ability();
		self::register_get_dispute_ability();
		self::register_get_authorizations_ability();
		self::register_get_authorizations_summary_ability();
		self::register_get_deposits_ability();
		self::register_get_deposits_summary_ability();
		self::register_get_payment_intent_ability();
		self::register_get_charge_ability();
		self::register_get_timeline_ability();
		self::register_get_active_loan_summary_ability();
	}

	/**
	 * Execute callback for `woocommerce-payments/get-account`.
	 *
	 * Delegates to `WC_REST_Payments_Accounts_Controller::get_account_data()`
	 * to preserve parity with the existing REST endpoint (controller adds
	 * `card_present_eligible`, `test_mode`, and `test_mode_onboarding` flags
	 * on top of the cached service payload).
	 *
	 * Reads from the local account cache — no remote API request is issued.
	 *
	 * @param mixed $input Unused (zero-arg ability); accepted to match the
	 *                     Abilities API execute_callback signature.
	 * @return array|\WP_Error Account data array, or WP_Error when WooPayments
	 *                         has not finished initializing.
	 */
	public static function execute_get_account( $input = null ) {
		unset( $input );

		if ( ! class_exists( '\WC_REST_Payments_Accounts_Controller' ) ) {
			return new \WP_Error(
				'wcpay_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		// The backing method does not read $this->api_client; it resolves
		// everything through the account service plus the mode singleton.
		// Guard on the account service (what the method actually needs);
		// the API client is only passed to satisfy the base controller's
		// constructor signature.
		$account_service = ( class_exists( '\WC_Payments' ) && method_exists( '\WC_Payments', 'get_account_service' ) )
			? \WC_Payments::get_account_service()
			: null;
		if ( null === $account_service ) {
			return new \WP_Error(
				'wcpay_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$controller = new \WC_REST_Payments_Accounts_Controller( \WC_Payments::get_payments_api_client() );
		$response   = $controller->get_account_data();

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}
		return is_array( $response ) ? $response : [];
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
	 */
	public static function reset_for_testing() {
		self::$category_registered  = false;
		self::$abilities_registered = false;
	}

	/**
	 * Execute callback — `get-deposits-overview`.
	 *
	 * @param mixed $input Unused.
	 * @return array|\WP_Error
	 */
	public static function execute_get_deposits_overview( $input = null ) {
		unset( $input );
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/deposits/overview-all' );
	}

	/**
	 * Execute callback — `get-transactions`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_transactions( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/transactions', is_array( $input ) ? $input : [] );
	}

	/**
	 * Execute callback — `get-transactions-summary`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_transactions_summary( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/transactions/summary', is_array( $input ) ? $input : [] );
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
	 * Execute callback — `get-disputes-summary`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_disputes_summary( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/disputes/summary', is_array( $input ) ? $input : [] );
	}

	/**
	 * Execute callback — `get-dispute`.
	 *
	 * @param mixed $input Ability input. Must include `dispute_id`.
	 * @return array|\WP_Error
	 */
	public static function execute_get_dispute( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_dispute_id',
				__( 'A non-empty `dispute_id` is required.', 'woocommerce-payments' )
			);
		}
		$dispute_id = $input['dispute_id'];
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/disputes/' . rawurlencode( $dispute_id ) );
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
	 * Execute callback — `get-authorizations-summary`.
	 *
	 * @param mixed $input Unused.
	 * @return array|\WP_Error
	 */
	public static function execute_get_authorizations_summary( $input = null ) {
		unset( $input );
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/authorizations/summary' );
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
	 * Execute callback — `get-deposits-summary`.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_get_deposits_summary( $input = null ) {
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/deposits/summary', is_array( $input ) ? $input : [] );
	}

	/**
	 * Execute callback — `get-payment-intent`.
	 *
	 * @param mixed $input Ability input. Must include `payment_intent_id`.
	 * @return array|\WP_Error
	 */
	public static function execute_get_payment_intent( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['payment_intent_id'] ) || ! is_string( $input['payment_intent_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_payment_intent_id',
				__( 'A non-empty `payment_intent_id` is required.', 'woocommerce-payments' )
			);
		}
		$intent_id = $input['payment_intent_id'];
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/payment_intents/' . rawurlencode( $intent_id ) );
	}

	/**
	 * Execute callback — `get-charge`.
	 *
	 * @param mixed $input Ability input. Must include `charge_id`.
	 * @return array|\WP_Error
	 */
	public static function execute_get_charge( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['charge_id'] ) || ! is_string( $input['charge_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_charge_id',
				__( 'A non-empty `charge_id` is required.', 'woocommerce-payments' )
			);
		}
		$charge_id = $input['charge_id'];
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/charges/' . rawurlencode( $charge_id ) );
	}

	/**
	 * Execute callback — `get-timeline`.
	 *
	 * @param mixed $input Ability input. Must include `intention_id`.
	 * @return array|\WP_Error
	 */
	public static function execute_get_timeline( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['intention_id'] ) || ! is_string( $input['intention_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_intention_id',
				__( 'A non-empty `intention_id` is required.', 'woocommerce-payments' )
			);
		}
		$intent_id = $input['intention_id'];
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/timeline/' . rawurlencode( $intent_id ) );
	}

	/**
	 * Execute callback — `get-active-loan-summary`.
	 *
	 * @param mixed $input Unused.
	 * @return array|\WP_Error
	 */
	public static function execute_get_active_loan_summary( $input = null ) {
		unset( $input );
		return self::delegate_to_rest_controller( 'GET', '/wc/v3/payments/capital/active_loan_summary' );
	}

	/**
	 * Register the `woocommerce-payments/get-account` ability.
	 *
	 * Zero-arg read returning the merchant's WooPayments account state —
	 * onboarding status, country, currencies, KYC deadlines, test/live mode.
	 * Backed by `WC_REST_Payments_Accounts_Controller::get_account_data()`.
	 *
	 * Unlike the other 14 read abilities, the execute callback for this
	 * ability calls the controller directly rather than going through
	 * `delegate_to_rest_controller()` (and therefore through `rest_do_request()`).
	 * The backing method takes no `WP_REST_Request`, the controller has no
	 * REST-lifecycle middleware to gain from in this codebase, and the data
	 * source is a local cache — the direct call avoids the one-time
	 * `rest_get_server()` bootstrap cost on CLI / cron / agent execution
	 * contexts where this is often the first ability invoked. If REST
	 * middleware ever lands for `/payments/accounts`, revisit this choice.
	 *
	 * @return void
	 */
	private static function register_get_account_ability() {
		wp_register_ability(
			'woocommerce-payments/get-account',
			[
				'label'               => __( 'Get WooPayments account state', 'woocommerce-payments' ),
				'description'         => __( 'Return the merchant\'s WooPayments account state: onboarding status, country, store and customer currencies, KYC requirements, deadlines, and test/live mode flags.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::zero_arg_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_account' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				// output_schema deliberately omitted — the payload shape comes
				// straight from the backing controller and we don't want to
				// couple this registrar to a specific structure here.
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-deposits-overview` ability.
	 *
	 * @return void
	 */
	private static function register_get_deposits_overview_ability() {
		wp_register_ability(
			'woocommerce-payments/get-deposits-overview',
			[
				'label'               => __( 'Get payouts overview', 'woocommerce-payments' ),
				'description'         => __( 'Return a per-currency overview of upcoming and recent payouts (Stripe deposits). Answers \'when is my next payout and how much?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::zero_arg_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_deposits_overview' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-transactions` ability.
	 *
	 * @return void
	 */
	private static function register_get_transactions_ability() {
		wp_register_ability(
			'woocommerce-payments/get-transactions',
			[
				'label'               => __( 'List transactions', 'woocommerce-payments' ),
				'description'         => __( 'List WooPayments transactions with filters (date range, type, source device, channel, customer country, risk level, currency, search). Answers \'show me transactions where X\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::transactions_list_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_transactions' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-transactions-summary` ability.
	 *
	 * @return void
	 */
	private static function register_get_transactions_summary_ability() {
		wp_register_ability(
			'woocommerce-payments/get-transactions-summary',
			[
				'label'               => __( 'Get transactions summary', 'woocommerce-payments' ),
				'description'         => __( 'Return aggregate counts and totals for a transactions filter. Answers \'how many transactions / how much volume in this window?\' without paging the list.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::filters_only( self::transactions_list_input_schema() ),
				'execute_callback'    => [ __CLASS__, 'execute_get_transactions_summary' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
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
	 * Register the `woocommerce-payments/get-disputes-summary` ability.
	 *
	 * @return void
	 */
	private static function register_get_disputes_summary_ability() {
		wp_register_ability(
			'woocommerce-payments/get-disputes-summary',
			[
				'label'               => __( 'Get disputes summary', 'woocommerce-payments' ),
				'description'         => __( 'Return aggregate counts of disputes by status. Answers \'how many disputes are pending response right now?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::filters_only( self::disputes_list_input_schema() ),
				'execute_callback'    => [ __CLASS__, 'execute_get_disputes_summary' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-dispute` ability.
	 *
	 * @return void
	 */
	private static function register_get_dispute_ability() {
		wp_register_ability(
			'woocommerce-payments/get-dispute',
			[
				'label'               => __( 'Get dispute by ID', 'woocommerce-payments' ),
				'description'         => __( 'Look up a single dispute by ID. Answers \'what evidence is needed for dispute du_X and by when?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => (object) [],
					'properties'           => [
						'dispute_id' => [
							'type'        => 'string',
							'description' => 'Stripe dispute ID (starts with `du_`). Not to be confused with the deposit/payout ID prefix `dp_`.',
							'pattern'     => '^du_',
						],
					],
					'required'             => [ 'dispute_id' ],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_dispute' ],
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
	 * Register the `woocommerce-payments/get-authorizations-summary` ability.
	 *
	 * @return void
	 */
	private static function register_get_authorizations_summary_ability() {
		wp_register_ability(
			'woocommerce-payments/get-authorizations-summary',
			[
				'label'               => __( 'Get authorizations summary', 'woocommerce-payments' ),
				'description'         => __( 'Return aggregate counts and total authorized amount for pending authorizations. Answers \'how much money is held in uncaptured authorizations?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::zero_arg_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_authorizations_summary' ],
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
	 * Register the `woocommerce-payments/get-deposits-summary` ability.
	 *
	 * @return void
	 */
	private static function register_get_deposits_summary_ability() {
		wp_register_ability(
			'woocommerce-payments/get-deposits-summary',
			[
				'label'               => __( 'Get payouts summary', 'woocommerce-payments' ),
				'description'         => __( 'Return aggregate counts and totals of payouts by status and currency. Answers \'how many payouts have I received this month?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::filters_only( self::deposits_list_input_schema() ),
				'execute_callback'    => [ __CLASS__, 'execute_get_deposits_summary' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-payment-intent` ability.
	 *
	 * @return void
	 */
	private static function register_get_payment_intent_ability() {
		wp_register_ability(
			'woocommerce-payments/get-payment-intent',
			[
				'label'               => __( 'Get payment intent by ID', 'woocommerce-payments' ),
				'description'         => __( 'Look up a single payment intent by Stripe ID (pi_…). Answers \'what is the state of intent pi_X?\' during incident response.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => (object) [],
					'properties'           => [
						'payment_intent_id' => [
							'type'        => 'string',
							'description' => 'Stripe payment intent ID (starts with `pi_`).',
							'pattern'     => '^pi_',
						],
					],
					'required'             => [ 'payment_intent_id' ],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_payment_intent' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-charge` ability.
	 *
	 * @return void
	 */
	private static function register_get_charge_ability() {
		wp_register_ability(
			'woocommerce-payments/get-charge',
			[
				'label'               => __( 'Get charge by ID', 'woocommerce-payments' ),
				'description'         => __( 'Look up a single charge by Stripe ID (ch_… or py_…). Answers \'what happened with charge ch_X?\'.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => (object) [],
					'properties'           => [
						'charge_id' => [
							'type'        => 'string',
							'description' => 'Stripe charge ID (starts with `ch_` or `py_`).',
							'pattern'     => '^(ch_|py_)',
						],
					],
					'required'             => [ 'charge_id' ],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_charge' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-timeline` ability.
	 *
	 * The input field is named `intention_id` (rather than `payment_intent_id`
	 * as used by `get-payment-intent`) to match the URL parameter on the
	 * backing route `/payments/timeline/(?P<intention_id>\w+)`. The two
	 * names refer to the same Stripe `pi_…` identifier. The pattern is
	 * restricted to `^pi_` deliberately: setup intents (`seti_…`) have
	 * different timeline semantics and the backing endpoint is not designed
	 * for them — widening this pattern needs a backing-endpoint review.
	 *
	 * @return void
	 */
	private static function register_get_timeline_ability() {
		wp_register_ability(
			'woocommerce-payments/get-timeline',
			[
				'label'               => __( 'Get timeline for payment intent', 'woocommerce-payments' ),
				'description'         => __( 'Return the chronological event timeline for a payment intent (created → succeeded → refunded → disputed). Helps reconstruct what happened to one transaction. Takes `intention_id` (the same Stripe `pi_…` identifier accepted by `get-payment-intent` as `payment_intent_id` — both names exist because they mirror the underlying REST route parameters).', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => (object) [],
					'properties'           => [
						'intention_id' => [
							'type'        => 'string',
							'description' => 'Stripe payment intent ID (starts with `pi_`). Same identifier accepted by `get-payment-intent` under the field name `payment_intent_id`.',
							'pattern'     => '^pi_',
						],
					],
					'required'             => [ 'intention_id' ],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_timeline' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				'meta'                => self::read_meta(),
			]
		);
	}

	/**
	 * Register the `woocommerce-payments/get-active-loan-summary` ability.
	 *
	 * @return void
	 */
	private static function register_get_active_loan_summary_ability() {
		wp_register_ability(
			'woocommerce-payments/get-active-loan-summary',
			[
				'label'               => __( 'Get active Capital loan summary', 'woocommerce-payments' ),
				'description'         => __( 'Return the merchant\'s active Stripe Capital loan summary. Answers \'how much do I still owe on my Capital loan and what is the daily repayment rate?\'. Returns an empty shape if no loan is active.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => self::zero_arg_input_schema(),
				'execute_callback'    => [ __CLASS__, 'execute_get_active_loan_summary' ],
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

	/**
	 * Delegate to a REST route via `rest_do_request()`.
	 *
	 * Builds a WP_REST_Request from the ability's input, dispatches through
	 * the REST router (which handles controller instantiation and its
	 * dependencies), then unwraps both `WP_REST_Response` and raw-array
	 * return shapes. Translates pagination/sort keys at the boundary (see
	 * `PAGINATION_KEY_MAP`).
	 *
	 * Shape 2 from the abilities-api skill — used for low-stakes reads with
	 * no telemetry side effects. Backing controllers in WooPayments either
	 * call `forward_request()` against the API client or hand off to a
	 * Request class via `handle_rest_request()` — neither path emits
	 * analytics events.
	 *
	 * Permission model — the request runs through `rest_do_request()`, so the
	 * backing route's own `permission_callback`
	 * (`WC_Payments_REST_Controller::check_permission()` → `manage_woocommerce`)
	 * fires in addition to the ability layer's `current_user_can_manage_woocommerce()`.
	 * Currently both gate on the same capability so the double-check is harmless,
	 * but if the ability layer ever tightens to a more granular capability the
	 * backing route's permission check must move in lockstep.
	 *
	 * Caching — no caching is added at the ability layer; whether a backing
	 * controller caches is its concern (e.g. `get-account` reads from a local
	 * cache, others issue fresh API calls each time). Don't add a transient
	 * layer here without coordinating with every backing controller.
	 *
	 * Performance — outside a REST request lifecycle (CLI, cron), the first
	 * call pays a one-time `rest_get_server()` + `rest_api_init` bootstrap
	 * cost.
	 *
	 * @param string              $http_method HTTP method (GET, POST, …).
	 * @param string              $route       REST route path (e.g. `/wc/v3/payments/transactions`).
	 * @param array<string,mixed> $params      Request parameters (query/body).
	 * @return array|\WP_Error Unwrapped response data, or WP_Error on failure.
	 */
	private static function delegate_to_rest_controller( $http_method, $route, $params = [] ) {
		// Translate WP-Core REST pagination/sort keys to the names the
		// WooPayments Paginated request class consumes.
		foreach ( self::PAGINATION_KEY_MAP as $agent_key => $request_key ) {
			if ( array_key_exists( $agent_key, $params ) ) {
				$params[ $request_key ] = $params[ $agent_key ];
				unset( $params[ $agent_key ] );
			}
		}

		$request = new \WP_REST_Request( $http_method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response instanceof \WP_REST_Response ) {
			if ( $response->is_error() ) {
				return $response->as_error();
			}
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}
		return is_array( $response ) ? $response : [];
	}
}
