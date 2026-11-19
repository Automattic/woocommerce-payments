<?php
/**
 * Class AbilitiesRegistrar
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooPayments with the WordPress Abilities API.
 *
 * Registers WooPayments read and write abilities under the shared
 * `woocommerce` category.
 * All abilities gate on `manage_woocommerce`. Read abilities are registered
 * by default (`woocommerce_payments_read_abilities_enabled`); write abilities
 * are opt-in (`woocommerce_payments_write_abilities_enabled`). The deprecated
 * `woocommerce_payments_abilities_enabled` filter still toggles both.
 *
 * Delegation errors are logged under the `woopayments-abilities` source.
 */
class AbilitiesRegistrar {

	/**
	 * Category slug for every WooPayments ability. The `woocommerce`
	 * category is registered by WooCommerce Core; plugin ownership lives
	 * in the ability namespace (`woocommerce-payments/*`).
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Maps WP-Core REST pagination/sort keys (used by ability input_schema)
	 * to the names `Paginated::from_rest_request()` reads. Without this
	 * translation, list abilities ignore caller pagination/sort input.
	 * `page` passes through unchanged.
	 *
	 * @var array<string,string>
	 */
	const PAGINATION_KEY_MAP = [
		'per_page' => 'pagesize',
		'orderby'  => 'sort',
		'order'    => 'direction',
	];

	/**
	 * Read-only ability definition classes, registered by default.
	 *
	 * @var class-string<AbilityDefinition>[]
	 */
	private const READ_ABILITY_CLASSES = [
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
		\WCPay\Internal\Abilities\Domain\GetBalance::class,
		\WCPay\Internal\Abilities\Domain\GetFraudOutcomes::class,
		\WCPay\Internal\Abilities\Domain\GetFeesSummary::class,
	];

	/**
	 * Write ability definition classes (annotated `readonly => false`),
	 * registered only when explicitly enabled.
	 *
	 * @var class-string<AbilityDefinition>[]
	 */
	private const WRITE_ABILITY_CLASSES = [
		\WCPay\Internal\Abilities\Domain\RefundCharge::class,
		\WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence::class,
		\WCPay\Internal\Abilities\Domain\AcceptDispute::class,
		\WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile::class,
	];

	/**
	 * Initialize the abilities registration.
	 *
	 * The enablement filters are read in `append_classes()`, when WooCommerce
	 * registers abilities on `wp_abilities_api_init` (after `init`), so
	 * callbacks added from a theme's `functions.php` take effect.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::woo_abilities_loader_available() ) {
			add_filter( 'woocommerce_ability_definition_classes', [ __CLASS__, 'append_classes' ] );
		}
	}

	/**
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param mixed $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( $classes ): array {
		if ( ! is_array( $classes ) ) {
			$classes = [];
		}

		return array_merge( $classes, self::get_enabled_classes() );
	}

	/**
	 * Ability classes enabled by the read, write and deprecated filters.
	 *
	 * @return class-string<AbilityDefinition>[]
	 */
	private static function get_enabled_classes(): array {
		$read_enabled  = true;
		$write_enabled = false;

		// Only consult the deprecated filter when something is hooked to it, so
		// its `false` default does not switch off the new read default.
		if ( has_filter( 'woocommerce_payments_abilities_enabled' ) ) {
			/**
			 * Filter whether WooPayments' Abilities API registrations are active.
			 *
			 * Any value other than `false` enables both read and write abilities,
			 * and `false` disables both. Both remain overridable by the filters below.
			 *
			 * @since 10.8.0
			 * @deprecated 11.4.0 Read abilities are enabled by default. Use `woocommerce_payments_write_abilities_enabled`
			 *                    to enable write abilities, or `woocommerce_payments_read_abilities_enabled` to disable read abilities.
			 *
			 * @param bool $enabled Whether to register WooPayments abilities. Default false.
			 * @return bool
			 */
			$legacy_enabled = false !== apply_filters_deprecated(
				'woocommerce_payments_abilities_enabled',
				[ false ],
				'11.4.0',
				'woocommerce_payments_read_abilities_enabled and woocommerce_payments_write_abilities_enabled'
			);
			$read_enabled   = $legacy_enabled;
			$write_enabled  = $legacy_enabled;
		}

		/**
		 * Filter whether WooPayments' read-only abilities are registered.
		 *
		 * @since 11.4.0
		 *
		 * @param bool $enabled Whether to register read-only abilities. Default true,
		 *                      or the result of the deprecated `woocommerce_payments_abilities_enabled` filter when it is used.
		 * @return bool
		 */
		$read_enabled = self::to_bool( apply_filters( 'woocommerce_payments_read_abilities_enabled', $read_enabled ), $read_enabled );

		/**
		 * Filter whether WooPayments' write abilities are registered. Write
		 * abilities perform consequential actions, such as refunds and
		 * dispute responses.
		 *
		 * @since 11.4.0
		 *
		 * @param bool $enabled Whether to register write abilities. Default false,
		 *                      or the result of the deprecated `woocommerce_payments_abilities_enabled` filter when it is used.
		 * @return bool
		 */
		$write_enabled = self::to_bool( apply_filters( 'woocommerce_payments_write_abilities_enabled', $write_enabled ), $write_enabled );

		return array_merge(
			$read_enabled ? self::READ_ABILITY_CLASSES : [],
			$write_enabled ? self::WRITE_ABILITY_CLASSES : []
		);
	}

	/**
	 * Permission callback for every WooPayments ability — mirrors
	 * `WC_Payments_REST_Controller::check_permission()`.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_woocommerce() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Delegate to a REST route via `rest_do_request()`. Translates
	 * pagination/sort keys (see `PAGINATION_KEY_MAP`) and unwraps the
	 * response. Permissions are enforced by the backing route.
	 *
	 * @param string              $http_method HTTP method (GET, POST, …).
	 * @param string              $route       REST route path (e.g. `/wc/v3/payments/transactions`).
	 * @param array<string,mixed> $params      Request parameters (query/body).
	 * @return array|\WP_Error Unwrapped response data, or WP_Error on failure.
	 */
	public static function delegate_to_rest_controller( $http_method, $route, $params = [] ) {
		// When the caller supplies both the agent-facing key (e.g. `per_page`)
		// and the canonical one (e.g. `pagesize`), the canonical key wins.
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
			$response = $response->get_data();
		}

		return is_array( $response ) ? $response : [];
	}

	/**
	 * Coerce a filtered toggle to a boolean, falling back to the default for
	 * values that are not boolean-like (for example arrays or objects).
	 *
	 * @param mixed $value         Filtered value.
	 * @param bool  $default_value Value to use when `$value` is not boolean-like.
	 * @return bool
	 */
	private static function to_bool( $value, bool $default_value ): bool {
		$bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		return null === $bool ? $default_value : $bool;
	}

	/**
	 * Whether WooCommerce 10.9's AbilitiesLoader is available.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}
}
