<?php
/**
 * REST API Subscriptions controller
 *
 * Handles requests to the /subscriptions endpoint.
 *
 * @package WooCommerce Subscriptions\Rest Api
 * @since   3.1.0
 */

defined( 'ABSPATH' ) || exit;

class WC_REST_Subscriptions_Controller extends WC_REST_Orders_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions';

	/**
	 * The post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_subscription';

	/**
	 * Register the routes for the subscriptions endpoint.
	 *
	 * -- Inherited --
	 * GET|POST /subscriptions
	 * GET|PUT|DELETE /subscriptions/<subscription_id>
	 *
	 * -- Subscription specific --
	 * GET /subscriptions/status
	 * GET /subscriptions/<subscription_id>/orders
	 *
	 * @since 3.1.0
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route( $this->namespace, "/{$this->rest_base}/statuses", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_statuses' ),
				'permission_callback' => '__return_true',
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, "/{$this->rest_base}/(?P<id>[\d]+)/orders", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_subscription_orders' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Gets the request object. Return false if the ID is not a subscription.
	 *
	 * @since  3.1.0
	 * @param  int $id Object ID.
	 * @return WC_Subscription|bool
	 */
	protected function get_object( $id ) {
		$subscription = wcs_get_subscription( $id );

		if ( ! $subscription || ! is_a( $subscription, 'WC_Subscription' ) ) {
			return false;
		}

		return $subscription;
	}

	/**
	 * Prepare a single subscription output for response.
	 *
	 * @since  3.1.0
	 *
	 * @param  WC_Data         $object  Subscription object.
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_object_for_response( $object, $request ) {
		$response = parent::prepare_object_for_response( $object, $request );

		// When generating the `/subscriptions/[id]/orders` response this function is called to generate related-order data so exit early if this isn't a subscription.
		if ( ! wcs_is_subscription( $object ) ) {
			return $response;
		}

		// Add subscription specific data to the base order response data.
		$response->data['billing_period']   = $object->get_billing_period();
		$response->data['billing_interval'] = $object->get_billing_interval();

		foreach ( wcs_get_subscription_date_types() as $date_type => $date_name ) {
			$date = $object->get_date( wcs_normalise_date_type_key( $date_type ) );
			$response->data[ $date_type . '_date_gmt' ] = ( ! empty( $date ) ) ? wc_rest_prepare_date_response( $date ) : '';
		}

		// Some base WC_Order dates need to be pulled from the subscription object to be correct.
		$response->data['date_paid']          = wc_rest_prepare_date_response( $object->get_date_paid(), false );
		$response->data['date_paid_gmt']      = wc_rest_prepare_date_response( $object->get_date_paid() );
		$response->data['date_completed']     = wc_rest_prepare_date_response( $object->get_date_completed(), false );
		$response->data['date_completed_gmt'] = wc_rest_prepare_date_response( $object->get_date_completed() );

		// Include resubscribe data.
		$resubscribed_subscriptions                  = array_filter( $object->get_related_orders( 'ids', 'resubscribe' ), 'wcs_is_subscription' );
		$response->data['resubscribed_from']         = strval( $object->get_meta( '_subscription_resubscribe' ) );
		$response->data['resubscribed_subscription'] = strval( reset( $resubscribed_subscriptions ) ); // Subscriptions can only be resubscribed to once so return the first and only element.

		// Include the removed line items.
		$response->data['removed_line_items'] = array();

		foreach ( $object->get_items( 'line_item_removed' ) as $item ) {
			$response->data['removed_line_items'][] = $this->get_order_item_data( $item );
		}

		// Remove non-subscription properties
		unset( $response->data['cart_hash'] );
		unset( $response->data['transaction_id'] );

		return $response;
	}

	/**
	 * Gets the /subscriptions/statuses response.
	 *
	 * @since 3.1.0
	 * @return WP_REST_Response The response object.
	 */
	public function get_statuses() {
		return rest_ensure_response( wcs_get_subscription_statuses() );
	}

	/**
	 * Gets the /subscriptions/[id]/orders response.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request            $request  The request object.
	 * @return WP_Error|WP_REST_Response $response The response or an error if one occurs.
	 */
	public function get_subscription_orders( $request ) {
		$id = absint( $request['id'] );

		if ( empty( $id ) || ! wcs_is_subscription( $id ) ) {
			return new WP_Error( 'woocommerce_rest_invalid_shop_subscription_id', __( 'Invalid subscription ID.', 'woocommerce-subscriptions' ), array( 'status' => 404 ) );
		}

		$subscription = wcs_get_subscription( $id );

		if ( ! $subscription ) {
			return new WP_Error( 'woocommerce_rest_invalid_shop_subscription_id', sprintf( __( 'Failed to load subscription object with the ID %d.', 'woocommerce-subscriptions' ), $id ), array( 'status' => 404 ) );
		}

		$orders = array();

		foreach ( array( 'parent', 'renewal', 'switch' ) as $order_type ) {
			foreach ( $subscription->get_related_orders( 'ids', $order_type ) as $order_id ) {

				if ( ! wc_rest_check_post_permissions( 'shop_order', 'read', $order_id ) ) {
					continue;
				}

				// Validate that the order can be loaded before trying to generate a response object for it.
				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					continue;
				}

				$response = $this->prepare_object_for_response( $order, $request );

				// Add the order's relationship to the response.
				$response->data['order_type'] = $order_type . '_order';

				$orders[] = $this->prepare_response_for_collection( $response );
			}
		}

		$response = rest_ensure_response( $orders );
		$response->header( 'X-WP-Total', count( $orders ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return apply_filters( 'wcs_rest_subscription_orders_response', $response, $request );
	}

	/**
	 * Overrides WC_REST_Orders_Controller::get_order_statuses() so that subscription statuses are
	 * validated correctly.
	 *
	 * @since 3.1.0
	 * @return array An array of valid subscription statuses.
	 */
	protected function get_order_statuses() {
		$subscription_statuses = array();

		foreach ( wcs_get_subscription_statuses() as $status => $status_name ) {
			$subscription_statuses[] = str_replace( 'wc-', '', $status );
		}

		return $subscription_statuses;
	}

	/**
	 * Prepares a single subscription for creation or update.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating If the request is for creating a new object.
	 * @return WP_Error|WC_Subscription
	 */
	public function prepare_object_for_database( $request, $creating = false ) {
		$id           = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$subscription = new WC_Subscription( $id );
		$schema       = $this->get_item_schema();
		$data_keys    = array_keys( array_filter( $schema['properties'], array( $this, 'filter_writable_props' ) ) );

		// Prepare variables for properties which need to be saved late (like status) or in a group (dates and payment data).
		$status         = '';
		$payment_method = '';
		$payment_meta   = array();
		$dates          = array();

		// If the start date is not set in the request, set its default to now.
		if ( ! isset( $request['start_date'] ) ) {
			$request['start_date'] = gmdate( 'Y-m-d H:i:s' );
		}

		// Both setting (set_status()) and updating (update_status()) are valid ways for requests to set a subscription's status.
		$status_transition = 'set';

		foreach ( $data_keys as $i => $key ) {
			$value = $request[ $key ];

			if ( is_null( $value ) ) {
				continue;
			}

			switch ( $key ) {
				case 'parent_id':
					$subscription->set_parent_id( $value );
					break;
				case 'transition_status':
					$status_transition = 'update';
				case 'status':
					// This needs to be done later so status changes take into account other data like dates.
					$status = $value;
					break;
				case 'billing':
				case 'shipping':
					$this->update_address( $subscription, $value, $key );
					break;
				case 'start_date':
				case 'trial_end':
				case 'next_payment_date':
				case 'cancelled_date':
				case 'end_date':
					// Group all the subscription date properties so they can be validated together.
					$dates[ $key ] = $value;
					break;
				case 'payment_method':
					$payment_method = $value;
					break;
				case 'payment_details':
					// Format the value in a way payment gateways expect so it can be validated.
					$payment_meta = $value;
					break;
				case 'line_items':
				case 'shipping_lines':
				case 'fee_lines':
					if ( is_array( $value ) ) {
						foreach ( $value as $item ) {
							if ( is_array( $item ) ) {
								if ( $this->item_is_null( $item ) || ( isset( $item['quantity'] ) && 0 === $item['quantity'] ) ) {
									$order->remove_item( $item['id'] );
								} else {
									$this->set_item( $subscription, $key, $item );
								}
							}
						}
					}
					break;
				case 'meta_data':
					if ( is_array( $value ) ) {
						foreach ( $value as $meta ) {
							$subscription->update_meta_data( $meta['key'], $meta['value'], isset( $meta['id'] ) ? $meta['id'] : '' );
						}
					}
					break;
				default:
					if ( is_callable( array( $subscription, "set_{$key}" ) ) ) {
						$subscription->{"set_{$key}"}( $value );
					}
					break;
			}
		}

		if ( ! empty( $payment_method ) ) {
			$this->update_payment_method( $subscription, $payment_method, $payment_meta );
		}

		if ( ! empty( $dates ) ) {
			// If the start date is not set in the request when a subscription is created with an active status, set its default to now.
			if ( 'active' === $status && empty( $id ) && ! isset( $dates['start_date'] ) ) {
				$dates['start_date'] = gmdate( 'Y-m-d H:i:s' );
			}

			try {
				$subscription->update_dates( $dates );
			} catch ( Exception $e ) {
				throw new WC_REST_Exception( 'woocommerce_rest_invalid_payment_data', sprintf( __( 'Subscription dates could not be set. Error message: %s', 'woocommerce-subscriptions' ), $e->getMessage() ), 400 );
			}
		}

		if ( ! empty( $status ) ) {
			if ( 'set' === $status_transition ) {
				$subscription->set_status( $status );
			} else {
				$subscription->update_status( $status );
				$request['status'] = $status; // Set the request status so parent::save_object() doesn't set it to the default 'pending' status.
			}
		}

		/**
		 * Filters an object before it is inserted via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`,
		 * refers to the object type slug.
		 *
		 * @param WC_Subscription $subscription The subscription object.
		 * @param WP_REST_Request $request      Request object.
		 * @param bool            $creating     If is creating a new object.
		 */
		return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}_object", $subscription, $request, $creating );
	}

	/**
	 * Adds additional item schema information for subscription requests.
	 *
	 * @since 3.1.0
	 * @return array
	 */
	public function get_item_schema() {
		$schema = parent::get_item_schema();

		// Base order schema overrides.
		$schema['properties']['status']['description'] = __( 'Subscription status.', 'woocommerce-subscriptions' );
		$schema['properties']['status']['enum']        = $this->get_order_statuses();

		$schema['properties']['created_via']['description']       = __( 'Where the subscription was created.', 'woocommerce-subscriptions' );
		$schema['properties']['currency']['description']          = __( 'Currency the subscription was created with, in ISO format.', 'woocommerce-subscriptions' );
		$schema['properties']['date_created']['description']      = __( "The date the subscription was created, in the site's timezone.", 'woocommerce-subscriptions' );
		$schema['properties']['date_created_gmt']['description']  = __( 'The date the subscription was created, as GMT.', 'woocommerce-subscriptions' );
		$schema['properties']['date_modified']['description']     = __( "The date the subscription was last modified, in the site's timezone.", 'woocommerce-subscriptions' );
		$schema['properties']['date_modified_gmt']['description'] = __( 'The date the subscription was last modified, as GMT.', 'woocommerce-subscriptions' );
		$schema['properties']['customer_id']['description']       = __( 'User ID who owns the subscription.', 'woocommerce-subscriptions' );

		unset( $schema['properties']['transaction_id'] );
		unset( $schema['properties']['refunds'] );
		unset( $schema['properties']['set_paid'] );
		unset( $schema['properties']['cart_hash'] );

		// Add subscription schema.
		$schema['properties'] += array(
			'transition_status' => array(
				'description' => __( 'The status to transition a subscription to.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
				'enum'        => $this->get_order_statuses(),
			),
			'billing_interval' => array(
				'description' => __( 'The number of billing periods between subscription renewals.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'billing_period' => array(
				'description' => __( 'Billing period for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
			),
			'payment_details' => array(
				'description' => __( 'Subscription payment details.', 'woocommerce-subscriptions' ),
				'type'        => 'object',
				'context'     => array( 'edit' ),
				'properties' => array(
					'post_meta' => array(
						'description' => __( 'Payment method meta and token in a post_meta_key: token format.', 'woocommerce-subscriptions' ),
						'type'        => 'object',
						'context'     => array( 'edit' ),
					),
					'user_meta' => array(
						'description' => __( 'Payment method meta and token in a user_meta_key : token format.', 'woocommerce-subscriptions' ),
						'type'        => 'object',
						'context'     => array( 'view' ),
					),
				),
			),
			'start_date' => array(
				'description' => __( "The subscription's start date, as GMT.", 'woocommerce-subscriptions' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
			),
			'trial_date' => array(
				'description' => __( "The subscription's trial date, as GMT.", 'woocommerce-subscriptions' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
			),
			'next_payment_date' => array(
				'description' => __( "The subscription's next payment date, as GMT.", 'woocommerce-subscriptions' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
			),
			'cancelled_date' => array(
				'description' => __( "The subscription's cancelled date, as GMT.", 'woocommerce-subscriptions' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
			),
			'end_date' => array(
				'description' => __( "The subscription's end date, as GMT.", 'woocommerce-subscriptions' ),
				'type'        => 'date-time',
				'context'     => array( 'view', 'edit' ),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 3.1.0
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		// Override the base order status description to be subscription specific.
		$params['status']['description'] = __( 'Limit result set to subscriptions which have specific statuses.', 'woocommerce-subscriptions' );
		return $params;
	}

	/**
	 * Gets an object's links to include in the response.
	 *
	 * Because this class also handles retreiving order data, we need
	 * to edit the links generated so the correct REST API href is included
	 * when its generated for an order.
	 *
	 * @since 3.1.0
	 *
	 * @param WC_Data         $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 * @return array                   Links for the given object.
	 */
	protected function prepare_links( $object, $request ) {
		$links = parent::prepare_links( $object, $request );

		if ( isset( $links['self'] ) && wcs_is_order( $object ) ) {
			$links['self'] = array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, 'orders', $object->get_id() ) ),
			);
		}

		return $links;
	}

	/**
	 * Updates a subscription's payment method and meta from data provided in a REST API request.
	 *
	 * @since 3.1.0
	 *
	 * @param WC_Subscription $subscription   The subscription to update.
	 * @param string          $payment_method The ID of the payment method to set.
	 * @param array           $payment_meta   The payment method meta.
	 */
	public function update_payment_method( $subscription, $payment_method, $payment_meta ) {
		$updating_subscription = (bool) $subscription->get_id();

		try {
			if ( $updating_subscription && ! array_key_exists( $payment_method, WCS_Change_Payment_Method_Admin::get_valid_payment_methods( $subscription ) ) ) {
				// translators: placeholder is the payment method ID.
				throw new Exception( sprintf( __( 'The %s payment gateway does not support admin changing the payment method.', 'woocommerce-subscriptions' ), $payment_method ) );
			}

			// Format the payment meta in the way payment gateways expect so it can be validated.
			$payment_method_meta = array();

			foreach ( $payment_meta as $table => $meta ) {
				foreach ( $meta as $meta_key => $value ) {
					$payment_method_meta[ $table ][ $meta_key ] = array( 'value' => $value );
				}
			}

			$subscription->set_payment_method( $payment_method, $payment_method_meta );
		} catch ( Exception $e ) {
			$subscription->set_payment_method();
			$subscription->save();
			// translators: 1$: gateway id, 2$: error message
			throw new WC_REST_Exception( 'woocommerce_rest_invalid_payment_data', sprintf( __( 'Subscription payment method could not be set to %1$s with error message: %2$s', 'woocommerce-subscriptions' ), $payment_method, $e->getMessage() ), 400 );
		}
	}
}
