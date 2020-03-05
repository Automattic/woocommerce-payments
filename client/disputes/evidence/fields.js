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
	// …
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
