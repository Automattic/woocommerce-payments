/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default [
	{
		key: 'general',
		title: __( 'General Evidence', 'woocommerce-payments' ),
		description: '',
		fields: [
			{
				key: 'product_description',
				label: __( 'Product Description', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'customer_name',
				label: __( 'Customer Name', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'customer_email_address',
				label: __( 'Customer Email', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'customer_signature',
				label: __( 'Customer Signature', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'billing_address',
				label: __( 'Customer Billing Address', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'customer_purchase_ip',
				label: __( 'Customer IP Address', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'receipt',
				label: __( 'Receipt', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'customer_communication',
				label: __( 'Customer Communication', 'woocommerce-payments' ),
				type: 'file',
			},
		],
	},
	{
		key: 'refund_policy_info',
		title: __( 'Refund Policy Info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'refund_policy',
				label: __( 'Refund Policy', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'refund_policy_disclosure',
				label: __( 'Refund policy disclosure', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'refund_refusal_explanation',
				label: __( 'Refund refusal explanation', 'woocommerce-payments' ),
				type: 'textarea',
			},
		],
		reason: 'credit_not_processed',
	},
	{
		key: 'duplicate_charge_info',
		title: __( 'Duplicate Charge Info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'duplicate_charge_id',
				label: __( 'Duplicate charge ID', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'duplicate_charge_explanation',
				label: __( 'Explanation of duplicate charge', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'duplicate_charge_documentation',
				label: __( 'Duplicate charge documentation', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'shipping_documentation',
				label: __( 'Shipping documentation', 'woocommerce-payments' ),
				type: 'file',
				denormalized: true,
				productType: 'physical_product',
			},
			{
				key: 'service_documentation',
				label: __( 'Service documentation', 'woocommerce-payments' ),
				type: 'file',
				denormalized: true,
				productType: 'offline_service',
			},
		],
		reason: 'duplicate',
	},
	{
		key: 'shipping_information',
		title: __( 'Shipping Information', 'woocommerce-payments' ),
		fields: [
			{
				key: 'shipping_carrier',
				label: __( 'Shipping carrier', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'shipping_tracking_number',
				label: __( 'Tracking number', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'shipping_documentation',
				label: __( 'Proof of shipping', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'shipping_date',
				label: __( 'Date of shipment', 'woocommerce-payments' ),
				type: 'text', // TODO use 'date'.
			},
			{
				key: 'shipping_address',
				label: __( 'Shipping Address', 'woocommerce-payments' ),
				type: 'textarea',
			},
		],
		reason: [ 'fraudulent', 'product_not_received', 'product_unacceptable', 'unrecognized' ],
		productType: 'physical_product',
	},
	{
		key: 'cancellation_policy_info',
		title: __( 'Cancellation Policy Info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'cancellation_policy',
				label: __( 'Cancellation Policy', 'woocommerce-payments' ),
				type: 'file',
			},
			{
				key: 'cancellation_policy_disclosure',
				label: __( 'Cancellation policy disclosure', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'cancellation_rebuttal',
				label: __( 'Cancellation Rebuttal', 'woocommerce-payments' ),
				type: 'textarea',
			},
		],
		reason: 'subscription_canceled',
	},
	{
		key: 'download_and_activity_logs',
		title: __( 'Download and activity logs', 'woocommerce-payments' ),
		fields: [
			{
				key: 'access_activity_log',
				type: 'file',
			},
		],
		reason: [ 'fraudulent', 'product_not_received', 'product_unacceptable', 'subscription_canceled', 'unrecognized' ],
		productType: 'digital_product_or_service',
	},
	{
		key: 'service_details',
		title: __( 'Service details', 'woocommerce-payments' ),
		fields: [
			{
				key: 'service_date',
				label: __( 'Service date', 'woocommerce-payments' ),
				type: 'text', // TODO use 'date'.
			},
			{
				key: 'service_documentation',
				label: __( 'Proof of service', 'woocommerce-payments' ),
				type: 'file',
			},
		],
		reason: [ 'fraudulent', 'product_not_received', 'product_unacceptable', 'subscription_canceled', 'unrecognized' ],
		productType: 'offline_service',
	},
	{
		key: 'uncategorized',
		title: __( 'Additional Details', 'woocommerce-payments' ),
		fields: [
			{
				key: 'uncategorized_text',
				label: __( 'Additional Details', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'uncategorized_file',
				type: 'file',
			},
		],
	},
];
