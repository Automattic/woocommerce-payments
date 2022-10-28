<?php
namespace WCPay\Core\Factories;


use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;
use WC_Payments_DB;
use WCPay\Core\DataTransferObjects\API\Address;
use WCPay\Core\DataTransferObjects\API\Charge;
use WCPay\Core\DataTransferObjects\API\Order;
use WCPay\Core\DataTransferObjects\API\Subscripton;
use WCPay\Core\DataTransferObjects\Data_Transfer_Object;
use WCPay\Core\DataTransferObjects\Response;

final class Create_Charge_Dto extends Data_Transfer_Object
{
	/**
	 * Create Charge DTO from wc pay response.
	 *
	 * @param Response $response
	 * @return Charge
	 */
	public static function create_from_wc_pay_response(Response $response) {

		$data = $response->get_code_from_response_data();
		$created = new \DateTime();
		$created->setTimestamp( $data['created'] );

		$wcpay_db = new WC_Payments_DB();
		$order = null;
		$order_data = $wcpay_db->order_from_charge_id( $data['id'] );
		if ( $order_data ) {
			$subscriptionsDto = [];
			if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] );
				foreach ( $subscriptions as $subscription ) {
					$subscriptionsDto[] = new Subscripton(
						$subscription->get_order_number(),
						$subscription->get_edit_order_url()
					);
				}
			}
			$customer_id = DataStore::get_existing_customer_id_from_order( $order_data );

			$customer_url =  $customer_id ? add_query_arg(
				[
					'page'      => 'wc-admin',
					'path'      => '/customers',
					'filter'    => 'single_customer',
					'customers' => $customer_id,
				],
				'admin.php'
			) : null;
			$order = new Order(
				$order_data->get_order_number(),
				$order_data->get_edit_order_url(),
				$customer_url,
				$subscriptionsDto
			);
		}

		$address = new Address(
			$billing_details['address']['city'] ?? '',
			$billing_details['address']['country'] ?? '',
			$billing_details['address']['line1'] ?? '',
			$billing_details['address']['line2'] ?? '',
			$billing_details['address']['postal_code'] ?? '',
			$billing_details['address']['state'] ?? ''
		);
		return new Charge(
			$data['id'],
			$data['amount'],
			$created,
			$data['payment_method_details'] ?? null,
			$data['payment_method'] ?? null,
			$data['amount_captured'] ?? null,
			$data['amount_refunded'] ?? null,
			$data['application_fee_amount'] ?? null,
			$data['balance_transaction'] ?? null,
			$address ?? null,
			$data['currency'] ?? null,
			$data['dispute'] ?? null,
			$data['disputed'] ?? null,
			$order,
			$data['outcome'] ?? null,
			$data['paid'] ?? null,
			$data['paydown'] ?? null,
			$data['payment_intent'] ?? null,
			$data['refunded'] ?? null,
			$data['refunds'] ?? null,
			$data['status'] ?? null,
			$data['captured'] ?? false
		);
	}

}
