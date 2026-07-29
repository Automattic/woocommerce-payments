<?php
/**
 * One-and-done recovery notice.
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Constants\Order_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * Nudges merchants who have taken exactly one live WooPayments sale (and
 * zero non-WooPayments orders) toward the WC Marketing Hub once that sale is
 * at least 7 days old. Surfaces inside WC admin / WC settings pages.
 */
class WC_Payments_One_And_Done_Notice extends WC_Payments_Abstract_Admin_Notice {

	/**
	 * Sticky option set the first time the store hits an irreversible
	 * disqualifier (≥2 WooPayments live orders, or ≥1 order through any other
	 * gateway). Once set, is_eligible() short-circuits before the transient
	 * lookup and order queries — the merchant has permanently aged out of the
	 * cohort and can never re-enter (orders aren't deleted in normal operation).
	 *
	 * @var string
	 */
	const OPTION_PERMANENTLY_INELIGIBLE = 'wcpay_one_and_done_permanently_ineligible';

	/**
	 * Number of days the merchant's first live transaction must be in the past
	 * before the nudge is shown.
	 *
	 * @var int
	 */
	const DAYS_THRESHOLD = 7;

	/**
	 * Registers the order-completion invalidator. Runs in both admin and
	 * non-admin contexts so storefront checkout / REST webhook completions
	 * drop the eligibility transient immediately.
	 *
	 * @return void
	 */
	public function init_global_hooks(): void {
		add_action( 'woocommerce_payment_complete', [ $this, 'invalidate_cache_on_order' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'invalidate_cache_on_order' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'invalidate_cache_on_order' ] );
	}

	/**
	 * Handles the "Promote my store" CTA. Treats the click as terminal
	 * engagement (suppresses the notice permanently for this user) and routes
	 * to the WC Admin Marketing Hub.
	 *
	 * @return void
	 */
	public function handle_cta(): void {
		$naming = $this->naming();
		if ( ! $this->verify_action_request( $naming->cta_query_arg(), $naming->cta_nonce_arg(), $naming->cta_nonce_action() ) ) {
			return;
		}

		$this->record_dismissal_and_redirect(
			add_query_arg(
				[
					'page' => 'wc-admin',
					'path' => '/marketing',
				],
				admin_url( 'admin.php' )
			),
			[ 'destination' => 'marketing' ]
		);
	}

	/**
	 * Drops the eligibility transient when an order that affects the
	 * predicate's count completes, so the notice self-clears within one page
	 * request rather than waiting for the 1-hour TTL.
	 *
	 * Any non-test-mode order completion can change eligibility — a 2nd
	 * WooPayments live order disqualifies, and a non-WooPayments order (cheque,
	 * COD, etc.) also disqualifies because the eligibility predicate counts
	 * total real-customer orders. Test-mode WooPayments orders are skipped:
	 * they don't represent real customers and the predicate ignores them.
	 *
	 * Short-circuits when there's no transient to invalidate to keep the
	 * per-order overhead at a single options-table read.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function invalidate_cache_on_order( $order_id ): void {
		if ( false === get_transient( $this->naming()->eligibility_transient_key() ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( 'woocommerce_payments' === $order->get_payment_method()
			&& Order_Mode::TEST === $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY ) ) {
			return;
		}

		$this->delete_eligibility_cache();
	}

	/**
	 * Snake_case identifier.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'one_and_done';
	}

	/**
	 * Eligibility predicate.
	 *
	 * Conditions:
	 * - Account is connected and valid.
	 * - Account is not a test-drive account.
	 * - Payments are enabled.
	 * - Stripe account is live.
	 * - Mode is neither test nor development.
	 * - Store has exactly one WooPayments live-mode order, zero non-WCPay orders.
	 * - That single order's date_created is at least DAYS_THRESHOLD days old.
	 *
	 * Strategy: two narrow indexed queries against post_meta rather than one
	 * wide unindexed scan.
	 *
	 *   Q1 — WooPayments live orders capped at 2: filtered server-side by
	 *        `_payment_method` + `_wcpay_mode`. Test-mode WCPay orders are
	 *        excluded by construction.
	 *   Q2 — non-WooPayments orders capped at 1: filtered server-side by
	 *        `_payment_method IN [other registered gateways]`. Caveat: misses
	 *        orders paid via since-uninstalled gateways. Acceptable for the
	 *        cohort — a one-and-done merchant uninstalling a gateway is a
	 *        vanishing edge case.
	 *
	 * Sets OPTION_PERMANENTLY_INELIGIBLE whenever an irreversible disqualifier
	 * is observed so subsequent calls short-circuit before reaching this query.
	 *
	 * @return bool
	 */
	protected function compute_eligibility(): bool {
		if ( ! $this->has_active_payments_account() ) {
			return false;
		}

		if ( ! $this->account->get_is_live() ) {
			return false;
		}

		if ( WC_Payments::mode()->is_test() ) {
			return false;
		}

		if ( WC_Payments::mode()->is_dev() ) {
			return false;
		}

		// Q1 — WooPayments live-mode orders, capped at 2. The 2-row cap only
		// distinguishes 0 / 1 / ≥2, so `orderby => 'none'` is intentional: it
		// avoids the ORDER BY filesort that made this slow on large stores
		// (WOOPMNT-6240). The exactly-1 case reads the single row's date below.
		$wcpay_live_orders = wc_get_orders(
			[
				'payment_method' => 'woocommerce_payments',
				'limit'          => 2,
				'orderby'        => 'none',
				'status'         => [ 'wc-completed', 'wc-processing' ],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => WC_Payments_Order_Service::WCPAY_MODE_META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => Order_Mode::PRODUCTION,
			]
		);

		$wcpay_live_count = count( $wcpay_live_orders );

		if ( $wcpay_live_count >= 2 ) {
			update_option( self::OPTION_PERMANENTLY_INELIGIBLE, '1' );
			return false;
		}

		if ( 1 !== $wcpay_live_count ) {
			return false;
		}

		// Q2 — any order through a different gateway disqualifies the merchant.
		$other_gateway_ids = array_diff(
			array_keys( WC()->payment_gateways()->payment_gateways() ),
			[ 'woocommerce_payments' ]
		);

		if ( ! empty( $other_gateway_ids ) ) {
			$non_wcpay_orders = wc_get_orders(
				[
					'payment_method' => $other_gateway_ids,
					'limit'          => 1,
					'orderby'        => 'none',
					'return'         => 'ids',
					'status'         => [ 'wc-completed', 'wc-processing' ],
				]
			);

			if ( ! empty( $non_wcpay_orders ) ) {
				update_option( self::OPTION_PERMANENTLY_INELIGIBLE, '1' );
				return false;
			}
		}

		$order_date = $wcpay_live_orders[0]->get_date_created();
		if ( ! $order_date ) {
			return false;
		}

		return time() >= $order_date->getTimestamp() + self::DAYS_THRESHOLD * DAY_IN_SECONDS;
	}

	/**
	 * Permanent-ineligibility short-circuit runs before the transient lookup
	 * so stores that have aged out never re-run the order queries.
	 *
	 * @return bool
	 */
	protected function is_eligible(): bool {
		if ( get_option( self::OPTION_PERMANENTLY_INELIGIBLE ) ) {
			return false;
		}
		return parent::is_eligible();
	}

	/**
	 * The dismiss/snooze keys carry a legacy `_at` suffix; pinned so merchants
	 * in the cohort don't lose their dismissals.
	 *
	 * @return array<string, string>
	 */
	protected function naming_overrides(): array {
		return [
			'dismissed_meta_key' => 'wcpay_one_and_done_notice_dismissed_at',
			'snoozed_meta_key'   => 'wcpay_one_and_done_notice_snoozed_at',
		];
	}
}
