<?php
/**
 * Class WC_Payments_Task_Disputes
 *
 * @package WooCommerce\Payments\Tasks
 */

namespace WooCommerce\Payments\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;
use WCPay\Database_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * WC Onboarding Task displayed if disputes awaiting response.
 *
 * Note: this task is separate to the Payments → Overview disputes task, which is defined in client/overview/task-list/tasks.js.
 */
class WC_Payments_Task_Disputes extends Task {

	private $api_client;
	private $database_cache;

	/**
	 * WC_Payments_Task_Disputes constructor.
	 */
	public function __construct( $api_client, $database_cache ) {
		$this->api_client     = $api_client;
		$this->database_cache = $database_cache;
		parent::__construct();
	}

	/**
	 * Gets the task ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'woocommerce_payments_disputes_task';
	}

	/**
	 * Gets the task title.
	 *
	 * @return string
	 */
	public function get_title() {
		$disputes_due_within_7d = $this->get_disputes_needing_response_within_days( 7 );
		$disputes_due_within_1d = $this->get_disputes_needing_response_within_days( 1 );

		if ( count( $disputes_due_within_7d ) === 1 ) {
			$dispute = $disputes_due_within_7d[0];
			$amount_formatted = wc_price( $dispute[ 'amount' ], [ 'currency' => strtoupper( $dispute[ 'currency' ] ) ] );
			if ( count( $disputes_due_within_1d ) > 0 ) {
				return sprintf(
					/* translators: %s is a currency formatted amount */
					__( 'Respond to a dispute for %s – Last day', 'woocommerce-payments' ),
					$amount_formatted
				);
			}
			return sprintf(
				/* translators: %s is a currency formatted amount */
				__( 'Respond to a dispute for %s', 'woocommerce-payments' ),
				$amount_formatted
			);
		}

		$currencies_map = [];
		foreach ( $disputes_due_within_7d as $dispute ) {
			if ( ! isset( $currencies_map[ $dispute[ 'currency' ] ] ) ) {
				$currencies_map[ $dispute[ 'currency' ] ] = 0;
			}
			$currencies_map[ $dispute[ 'currency' ] ] += $dispute[ 'amount' ];
		}

		$currencies = array_keys( $currencies_map );
		sort( $currencies );
		$formatted_amounts = [];
		foreach ( $currencies as $currency ) {
			$formatted_amounts[] = wc_price( $currencies_map[ $currency ], [ 'currency' => strtoupper( $currency ) ] );
		}
		$dispute_total_amounts = implode( ', ', $formatted_amounts );

		return sprintf(
			/* translators: %d is a number. %s is a currency formatted amounts (potentially multiple), eg: €10.00, $20.00 */
			__( 'Respond to %d active disputes for a total of %s', 'woocommerce-payments' ),
			count( $disputes_due_within_7d ),
			$dispute_total_amounts
		);
	}

	/**
	 * Get the parent list ID.
	 *
	 * This function prior to WC 6.4.0 was abstract and so is needed for backwards compatibility.
	 *
	 * @return string
	 */
	public function get_parent_id() {
		// WC 6.4.0 compatibility.
		if ( is_callable( 'parent::get_parent_id' ) ) {
			return parent::get_parent_id();
		}

		return 'extended';
	}

	/**
	 * Gets the task content.
	 *
	 * @return string
	 */
	public function get_content() {
		$disputes_due_within_7d = $this->get_disputes_needing_response_within_days( 7 );
		$disputes_due_within_1d = $this->get_disputes_needing_response_within_days( 1 );

		if ( count( $disputes_due_within_7d ) === 1 ) {
			$dispute = $disputes_due_within_7d[0];
			if ( count( $disputes_due_within_1d ) > 0 ) {
				return sprintf(
					/* translators: %s is time, eg: 11:59 PM */
					__( 'Respond today by %s', 'woocommerce-payments' ),
					(new \DateTime( $dispute['due_by'] ))->format( 'h:mm A' ) // TODO make sure time is in merchant's store timezone
				);
			}

			$due_by = new \DateTime( $dispute['due_by'] );

			// Convert merchant's store timezone to UTC.
			$timezone = new \DateTimeZone( wp_timezone_string() );
			$now      = new \DateTime( 'now', $timezone );
			$now->setTimezone( new \DateTimeZone( 'UTC' ) );

			$diff = $due_by->diff( $now );

			return sprintf(
				/* translators: %1$s is time, eg: Jan 1, 2021. %2$s is how many days left eg: 2 days */
				__( 'By %1$s – %2$s left to respond', 'woocommerce-payments' ),
				(new \DateTime( $dispute['due_by'] ))->format( 'MMM D, YYYY' ), // TODO make sure time is in merchant's store timezone
				_n( '%d day', '%d days', $diff->days, 'woocommerce-payments' ) // TODO make sure time is in merchant's store timezone and when it is 1 day left, it should say 1 day left, not 0 day
			);
		}

		$currencies_map = [];
		foreach ( $disputes_due_within_7d as $dispute ) {
			if ( ! isset( $currencies_map[ $dispute[ 'currency' ] ] ) ) {
				$currencies_map[ $dispute[ 'currency' ] ] = 0;
			}
			$currencies_map[ $dispute[ 'currency' ] ] += $dispute[ 'amount' ];
		}

		$currencies = array_keys( $currencies_map );
		sort( $currencies );
		$formatted_amounts = [];
		foreach ( $currencies as $currency ) {
			$formatted_amounts[] = wc_price( $currencies_map[ $currency ], [ 'currency' => strtoupper( $currency ) ] );
		}
		$dispute_total_amounts = implode( ', ', $formatted_amounts );

		return sprintf(
			/* translators: %s is a currency formatted amounts (potentially multiple), eg: €10.00, $20.00 */
			__( 'Respond to %d active disputes for a total of %s', 'woocommerce-payments' ),
			count( $disputes_due_within_7d ),
			$dispute_total_amounts
		);
	}

	/**
	 * Get the additional info.
	 *
	 * @return string
	 */
	public function get_additional_info() {
		return __( 'View and respond', 'woocommerce-payments' );
	}

	/**
	 * Gets the task's action label.
	 *
	 * @return string
	 */
	public function get_action_label() {
		return __( 'Disputes', 'woocommerce-payments' );
	}

	/**
	 * Gets the task's action URL.
	 *
	 * @return string
	 */
	public function get_action_url() {
		$disputes = $this->get_disputes_needing_response_within_days( 7 );
		if ( count ( $disputes ) === 1 ) {
			$dispute = $disputes[0];
			return admin_url(
				add_query_arg(
					[
						'page' => 'wc-admin',
						'path' => '%2Fpayments%2Fdisputes%2Fdetails',
						'id'   => $dispute[ 'dispute_id' ],
					],
					'admin.php'
				)
			);
		}

		return admin_url(
			add_query_arg(
				[
					'page'   => 'wc-admin',
					'path'   => '%2Fpayments%2Fdisputes',
					'filter' => 'awaiting_response',
				],
				'admin.php'
			)
		);
	}

	/**
	 * Get the estimated time to complete the task.
	 *
	 * @return string
	 */
	public function get_time() {
		return '';
	}

	/**
	 * Get whether the task is completed.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return false;
	}

	/**
	 * Get whether the task is visible.
	 *
	 * @return bool
	 */
	public function can_view() {
		return count( $this->get_disputes_needing_response_within_days( 7 ) ) > 0;
	}

	// TODO function doc-block.
	private function get_disputes_needing_response_within_days( $num_days ) {
		$to_return = [];

		$active_disputes = $this->get_disputes_needing_response();
		if ( ! $active_disputes ) {
			return $to_return;
		}

		foreach ( $active_disputes as $dispute ) {
			if ( ! $dispute['due_by'] ) {
				continue;
			}

			// TODO due_by does not carry timezone. It is possible that the server's timezone and merchant store's timezone is different. Is there a solution to get a more accurate time diff?
			// Assume server's time is UTC.
			$due_by = new \DateTime( $dispute['due_by'] );

			// Convert merchant's store timezone to UTC.
			$timezone = new \DateTimeZone( wp_timezone_string() );
			$now      = new \DateTime( 'now', $timezone );
			$now->setTimezone( new \DateTimeZone( 'UTC' ) );

			$diff = $due_by->diff( $now );
			if ( $due_by > $now ) {
				continue;
			}

			if ( $diff->days <= $num_days ) {
				$to_return[] = $dispute;
			}
		}

		return $to_return;
	}

	/**
	 * Get disputes that need response.
	 *
	 * @return mixed|null
	 */
	private function get_disputes_needing_response() {
		return $this->database_cache->get_or_add(
			'wcpay_active_dispute_cache',
			function() {
				return $this->api_client->get_disputes( [
					'pagesize' => 50,
    				'search'   => [ 'warning_needs_response', 'needs_response' ],
				] );
			},
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);
	}
}
