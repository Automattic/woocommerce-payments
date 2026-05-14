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
	 * All 15 WooPayments read abilities. The loader iterates this list,
	 * checks `is_a( $class, AbilityDefinition::class, true )` on each,
	 * and registers those that pass via wp_register_ability().
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
		\WCPay\Internal\Abilities\Domain\GetDisputes::class,
		\WCPay\Internal\Abilities\Domain\GetAuthorizations::class,
		\WCPay\Internal\Abilities\Domain\GetDeposits::class,
	];

	/**
	 * Tracks whether the category has been registered in this process to
	 * keep `register_category()` idempotent across action variants.
	 *
	 * @var bool
	 */
	private static $category_registered = false;

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
		// short-circuits and no abilities are registered for this request.
		if ( self::woo_abilities_loader_available() ) {
			add_filter( 'woocommerce_ability_definition_classes', [ __CLASS__, 'append_classes' ] );
		}

		// The abilities-api composer package fires the non-prefixed action
		// names; WP Core's merge target uses the `wp_` variants. Hook both
		// so the registrar works against either version of the API. The
		// idempotency guard in register_category() handles the dual-hook
		// double-fire scenario.
		add_action( 'abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
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
		self::$category_registered = false;
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
	 * On WC < 10.9 the filter wire is skipped and no abilities are registered.
	 *
	 * WC 10.9 also depends on WP 6.9, so wp_register_ability() is implicitly
	 * available wherever the loader exists.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}
}
