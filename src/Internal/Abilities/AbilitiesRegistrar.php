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
 * All abilities gate on `manage_woocommerce` and are gated by the
 * `woocommerce_payments_abilities_enabled` filter (default `false`).
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
	 * Ability definition classes appended to Woo Core's loader.
	 *
	 * @var class-string<AbilityDefinition>[]
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
		\WCPay\Internal\Abilities\Domain\RefundCharge::class,
		\WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence::class,
		\WCPay\Internal\Abilities\Domain\AcceptDispute::class,
		\WCPay\Internal\Abilities\Domain\UploadDisputeEvidenceFile::class,
		\WCPay\Internal\Abilities\Domain\GetBalance::class,
		\WCPay\Internal\Abilities\Domain\GetFraudOutcomes::class,
		\WCPay\Internal\Abilities\Domain\GetFeesSummary::class,
	];

	/**
	 * Initialize the abilities registration.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filter whether WooPayments' Abilities API registrations are active.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Whether to register WooPayments abilities. Default false.
		 * @return bool
		 */
		if ( false === apply_filters( 'woocommerce_payments_abilities_enabled', false ) ) {
			return;
		}

		if ( self::woo_abilities_loader_available() ) {
			add_filter( 'woocommerce_ability_definition_classes', [ __CLASS__, 'append_classes' ] );
		}
	}

	/**
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
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
	 * Whether WooCommerce 10.9's AbilitiesLoader is available.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}
}
