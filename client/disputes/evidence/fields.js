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
				display: __( 'Product Description', 'woocommerce-payments' ),
				control: 'textarea',
			},
			{
				key: 'customer_name',
				display: __( 'Customer Name', 'woocommerce-payments' ),
				control: 'text',
			},
			{
				key: 'customer_email_address',
				display: __( 'Customer Email', 'woocommerce-payments' ),
				control: 'text',
			},
			{
				key: 'customer_signature',
				display: __( 'Customer Signature', 'woocommerce-payments' ),
				control: 'file',
			},
			{
				key: 'billing_address',
				display: __( 'Customer Billing Address', 'woocommerce-payments' ),
				control: 'textarea',
			},
			{
				key: 'customer_purchase_ip',
				display: __( 'Customer IP Address', 'woocommerce-payments' ),
				control: 'text',
			},
			{
				key: 'receipt',
				display: __( 'Receipt', 'woocommerce-payments' ),
				control: 'file',
			},
			{
				key: 'customer_communication',
				display: __( 'Customer Communication', 'woocommerce-payments' ),
				control: 'file',
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
				display: __( 'Additional Details', 'woocommerce-payments' ),
				control: 'textarea',
			},
			{
				key: 'uncategorized_file',
				control: 'file',
			},
		],
	},
];
