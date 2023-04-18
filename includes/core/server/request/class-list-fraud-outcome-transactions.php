<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WC_Payments_Utils;
use WC_Payments_API_Client;
use WCPay\Constants\Fraud_Meta_Box_Type;

/**
 * Request class for getting intents.
 */
class List_Fraud_Outcome_Transactions extends Paginated {
	const DEFAULT_PARAMS = [
		'page'      => 1,
		'pagesize'  => 25,
		'sort'      => 'date',
		'direction' => 'desc',
	];

	/**
	 * Fraud outcome status.
	 *
	 * @var string $status
	 */
	private $status;

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::FRAUD_OUTCOMES_API . '/status/' . $this->status;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Sets the fraud outcome status.
	 *
	 * @param string $status Fraud outcome status.
	 */
	public function set_status( $status ) {
		$this->status = $status;
	}

	/**
	 * Sets the search param.
	 *
	 * @param array $search Search param.
	 */
	public function set_search( $search ) {
		$this->set_param( 'search', $search );
	}

	/**
	 * Sets the search term param.
	 *
	 * @param string $search_term Search term param.
	 */
	public function set_search_term( $search_term ) {
		$this->set_param( 'search_term', $search_term );
	}

	/**
	 * Sets the additional status param.
	 *
	 * @param string $additional_status Additional status param.
	 */
	public function set_additional_status( $additional_status ) {
		$this->set_param( 'additional_status', $additional_status );
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = parent::from_rest_request( $request );

		$search            = $request->get_param( 'search' ) ?? [];
		$status            = $request->get_param( 'status' );
		$search_term       = $request->get_param( 'search_term' ) ?? '';
		$additional_status = $request->get_param( 'additional_status' ) ?? '';

		$wcpay_request->set_status( $status );
		$wcpay_request->set_search( $search );
		$wcpay_request->set_search_term( $search_term );
		$wcpay_request->set_additional_status( $additional_status );

		return $wcpay_request;
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		$params = $this->get_params();

		$sort      = $params['sort'];
		$search    = $params['search'];
		$direction = $params['direction'];

		if ( ! is_array( $response ) ) {
			return $response;
		}

		// The fraud outcomes list doesn't hold the status of the payment intent, so we need
		// to remove the payments that aren't in the desired status anymore.
		$fraud_outcomes = array_reduce(
			$response,
			function ( $result, $current ) use ( $search ) {
				$outcome = $this->build_fraud_outcome_transactions_order_info( $current );

				// Removes the outcomes that are not pending review.
				$is_review_pending = 'requires_capture' === $outcome['payment_intent']['status'] && empty( $outcome['manual_review'] ) && Fraud_Meta_Box_Type::REVIEW === $outcome['fraud_meta_box_type'];
				if ( 'review' === $this->status && ! $is_review_pending ) {
					return $result;
				}

				// Removes the outcomes that are not blocked.
				$block_statuses   = [ Fraud_Meta_Box_Type::BLOCK, Fraud_Meta_Box_Type::REVIEW_BLOCKED ];
				$has_block_status = in_array( $outcome['fraud_meta_box_type'], $block_statuses, true );
				if ( 'block' === $this->status && ! $has_block_status ) {
					return $result;
				}

				// Performs the search.
				if ( is_array( $search ) && ! empty( $search ) ) {
					$found = array_reduce(
						$search,
						function ( $found, $term ) use ( $outcome ) {
							return $this->get_search_result( $found, $term, $outcome );
						},
						false
					);

					if ( ! $found ) {
						return $result;
					}
				}

				unset( $outcome['manual_review'] );

				$result[] = $outcome;

				return $result;
			},
			[]
		);

		// Handles the sorting.
		$compare_sorting = function ( $a, $b ) use ( $sort, $direction ) {
			return $this->get_sort_result( $a, $b, $sort, $direction );
		};

		usort( $fraud_outcomes, $compare_sorting );

		return $fraud_outcomes;
	}

	/**
	 * Builds the order info for fraud outcome transaction.
	 *
	 * The fraud outcome result doesn't give all the data required to list the on review transactions.
	 * So we need to fill in any empty data, such as payment_intent_id and the actual data from the payment intent.
	 *
	 * @param array $outcome Fraud outcome array.
	 *
	 * @return array
	 */
	private function build_fraud_outcome_transactions_order_info( $outcome ) {
		$order = wc_get_order( $outcome['order_id'] );

		$outcome['payment_intent']           = [];
		$outcome['payment_intent']['id']     = $outcome['payment_intent_id'] ?? $order->get_meta( '_intent_id' ) ?? $order->get_transaction_id();
		$outcome['payment_intent']['status'] = $order->get_meta( '_intention_status' );

		$outcome['amount']              = WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() );
		$outcome['currency']            = $order->get_currency();
		$outcome['customer_name']       = wc_clean( $order->get_billing_first_name() ) . ' ' . wc_clean( $order->get_billing_last_name() );
		$outcome['manual_review']       = $order->get_meta( '_wcpay_fraud_outcome_manual_entry' );
		$outcome['fraud_meta_box_type'] = $order->get_meta( '_wcpay_fraud_meta_box_type' );

		unset( $outcome['payment_intent_id'] );

		return $outcome;
	}

	/**
	 * Returns the result for a given search.
	 *
	 * @param bool   $found   If it was found already.
	 * @param string $term    Term to be searched.
	 * @param array  $outcome Fraud outcome object.
	 *
	 * @return bool
	 */
	private function get_search_result( $found, $term, $outcome ) {
		if ( $found ) {
			return $found;
		}

		// Search by order id.
		if ( preg_match( '/#(\d+)/', $term, $matches ) ) {
			return $matches[1] === (string) $outcome['order_id'];
		};

		// Search by customer name.
		return (bool) preg_match( "/{$term}/i", $outcome['customer_name'] );
	}

	/**
	 * Returns the sort result for the comparison of two items.
	 *
	 * @param array  $a         First item to be compared.
	 * @param array  $b         Second item to be compared.
	 * @param array  $sort      Sort key.
	 * @param string $direction Sort direction.
	 *
	 * @return int
	 */
	private function get_sort_result( $a, $b, $sort, $direction ) {
		$key = 'date' === $sort ? 'created' : $sort;

		if ( ! array_key_exists( $key, $a ) || ! array_key_exists( $key, $b ) ) {
			return 0;
		}

		$a = $a[ $key ];
		$b = $b[ $key ];

		if ( $a === $b ) {
			return 0;
		};

		if ( 'desc' === $direction ) {
			return $a < $b ? 1 : -1;
		};

		return $a < $b ? -1 : 1;
	}
}
