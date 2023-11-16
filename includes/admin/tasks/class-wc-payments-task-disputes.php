<?php
/**
 * Class WC_Payments_Task_Disputes
 *
 * @package WooCommerce\Payments\Tasks
 */

namespace WooCommerce\Payments\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;
use WCPay\Database_Cache;
use WC_Payments_Utils;
use WC_Payments_API_Client;

defined( 'ABSPATH' ) || exit;

/**
 * WC Onboarding Task displayed if disputes awaiting response.
 *
 * Note: this task is separate to the Payments → Overview disputes task, which is defined in client/overview/task-list/tasks.js.
 */
class WC_Payments_Task_Disputes extends Task {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * Database_Cache instance.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;


	/**
	 * Disputes due within 7 days.
	 *
	 * @var array|null
	 */
	private $disputes_due_within_7d;

	/**
	 * Disputes due within 1 day.
	 *
	 * @var array|null
	 */
	private $disputes_due_within_1d;

	/**
	 * WC_Payments_Task_Disputes constructor.
	 */
	public function __construct() {

		$this->api_client     = \WC_Payments::get_payments_api_client();
		$this->database_cache = \WC_Payments::get_database_cache();
		parent::__construct();
		$this->init();
	}

	/**
	 * Initialize the task.
	 */
	private function init() {
		$this->disputes_due_within_7d = $this->get_disputes_needing_response_within_days( 7 );
		$this->disputes_due_within_1d = $this->get_disputes_needing_response_within_days( 1 );
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
		if ( count( (array) $this->disputes_due_within_7d ) === 1 ) {
			$dispute          = $this->disputes_due_within_7d[0];
			$amount           = WC_Payments_Utils::interpret_stripe_amount( $dispute['amount'], $dispute['currency'] );
			$amount_formatted = WC_Payments_Utils::format_currency( $amount, $dispute['currency'] );
			if ( count( (array) $this->disputes_due_within_1d ) > 0 ) {
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

		$active_disputes = $this->get_disputes_needing_response();
		if ( ! is_array( $active_disputes ) || count( $active_disputes ) === 0 ) {
			return '';
		}

		$dispute_currencies = array_unique( array_column( $active_disputes, 'currency' ) );

		// If multiple currencies, use simple task title without total amounts.
		if ( count( $dispute_currencies ) > 1 ) {
			return sprintf(
				// translators: %d is a number greater than 1.
				__( 'Respond to %d active disputes', 'woocommerce-payments' ),
				count( $active_disputes )
			);
		}

		// If single currency, calculate total amount and include in task title.
		$dispute_total = array_reduce(
			$active_disputes,
			function ( $total, $dispute ) {
				return $total + ( $dispute['amount'] ?? 0 );
			},
			0
		);

		$dispute_total_formatted = WC_Payments_Utils::format_currency(
			WC_Payments_Utils::interpret_stripe_amount( $dispute_total, $dispute_currencies[0] ),
			$dispute_currencies[0]
		);

		return sprintf(
			/* translators: %d is a number greater than 1. %s is a formatted amount, eg: $10.00 */
			__( 'Respond to %1$d active disputes for a total of %2$s', 'woocommerce-payments' ),
			count( $active_disputes ),
			$dispute_total_formatted
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
	 * Gets the task subtitle.
	 *
	 * @return string
	 */
	public function get_additional_info() {
		if ( count( (array) $this->disputes_due_within_7d ) === 1 ) {
			$local_timezone    = new \DateTimeZone( wp_timezone_string() );
			$dispute           = $this->disputes_due_within_7d[0];
			$due_by_local_time = ( new \DateTime( $dispute['due_by'] ) )->setTimezone( $local_timezone );
			// Sum of Unix timestamp and timezone offset in seconds.
			$due_by_ts = $due_by_local_time->getTimestamp() + $due_by_local_time->getOffset();

			if ( count( (array) $this->disputes_due_within_1d ) > 0 ) {
				return sprintf(
					/* translators: %s is time, eg: 11:59 PM */
					__( 'Respond today by %s', 'woocommerce-payments' ),
					date_i18n( wc_time_format(), $due_by_ts )
				);
			}

			$now  = new \DateTime( 'now', $local_timezone );
			$diff = $now->diff( $due_by_local_time );

			return sprintf(
				/* translators: %1$s is a date, eg: Jan 1, 2021. %2$s is the number of days left, eg: 2 days. */
				__( 'By %1$s – %2$s left to respond', 'woocommerce-payments' ),
				date_i18n( wc_date_format(), $due_by_ts ),
				/* translators: %s is the number of days left, e.g. 1 day. */
				sprintf( _n( '%d day', '%d days', $diff->days, 'woocommerce-payments' ), $diff->days )
			);
		}

		if ( count( (array) $this->disputes_due_within_1d ) > 0 ) {
			return sprintf(
				/* translators: %d is the number of disputes. */
				__(
					'Final day to respond to %d of the disputes',
					'woocommerce-payments'
				),
				count( (array) $this->disputes_due_within_1d )
			);
		}

		return sprintf(
			/* translators: %d is the number of disputes. */
			__(
				'Last week to respond to %d of the disputes',
				'woocommerce-payments'
			),
			count( (array) $this->disputes_due_within_7d )
		);

	}

	/**
	 * Gets the task's action URL.
	 *
	 * @return string
	 */
	public function get_action_url() {
		$disputes = $this->disputes_due_within_7d;
		if ( count( (array) $disputes ) === 1 ) {
			$dispute = $disputes[0];
			return admin_url(
				add_query_arg(
					[
						'page' => 'wc-admin',
						'path' => '%2Fpayments%2Ftransactions%2Fdetails',
						'id'   => $dispute['charge_id'],
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
	 * Gets the task content.
	 *
	 * @return string
	 */
	public function get_content() {
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
		return count( (array) $this->disputes_due_within_7d ) > 0;
	}

	/**
	 * Get disputes needing response within the given number of days.
	 *
	 * @param int $num_days Number of days in the future to check for disputes needing response.
	 *
	 * @return array Disputes needing response within the given number of days.
	 */
	private function get_disputes_needing_response_within_days( $num_days ) {
		$to_return = [];

		$active_disputes = $this->get_disputes_needing_response();
		if ( ! is_array( $active_disputes ) ) {
			return $to_return;
		}

		foreach ( $active_disputes as $dispute ) {
			if ( ! $dispute['due_by'] ) {
				continue;
			}

			// Compare UTC times.
			$now_utc    = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
			$due_by_utc = new \DateTime( $dispute['due_by'], new \DateTimeZone( 'UTC' ) );

			if ( $now_utc > $due_by_utc ) {
				continue;
			}

			$diff = $now_utc->diff( $due_by_utc );
			// If the dispute is due within the given number of days, add it to the list.
			if ( $diff->days <= $num_days ) {
				$to_return[] = $dispute;
			}
		}

		return $to_return;
	}

	/**
	 * Gets disputes awaiting a response. ie have a 'needs_response' or 'warning_needs_response' status.
	 *
	 * @return array|null Array of disputes awaiting a response. Null on failure.
	 */
	private function get_disputes_needing_response() {
		return $this->database_cache->get_or_add(
			Database_Cache::ACTIVE_DISPUTES_KEY,
			function() {
				$response = $this->api_client->get_disputes(
					[
						'pagesize' => 50,
						'search'   => [ 'warning_needs_response', 'needs_response' ],
					]
				);

				$active_disputes = $response['data'] ?? [];

				// sort by due_by date ascending.
				usort(
					$active_disputes,
					function( $a, $b ) {
						$a_due_by = new \DateTime( $a['due_by'] );
						$b_due_by = new \DateTime( $b['due_by'] );

						return $a_due_by <=> $b_due_by;
					}
				);

				return $active_disputes;
			},
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);
	}
}
