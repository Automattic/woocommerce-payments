/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default [
	{
		key: 'general',
		title: __( 'General Evidence' ),
		description: '',
		fields: [
			{
				key: 'product_description',
				display: 'Product Description',
				control: 'textarea',
			},
			{
				key: 'customer_name',
				display: 'Customer Name',
				control: 'text',
			},
			{
				key: 'customer_email_address',
				display: 'Customer Email',
				control: 'text',
			},
			{
				key: 'customer_signature',
				display: 'Customer Signature',
				control: 'file',
			},
			{
				key: 'billing_address',
				display: 'Customer Billing Address',
				control: 'textarea',
			},
			{
				key: 'customer_purchase_ip',
				display: 'Customer IP Address',
				control: 'text',
			},
			{
				key: 'receipt',
				display: 'Receipt',
				control: 'file',
			},
			{
				key: 'customer_communication',
				display: 'Customer Communication',
				control: 'file',
			},
		],
	},
	// …
	{
		key: 'uncategorized',
		title: __( 'Additional Details' ),
		fields: [
			{
				key: 'uncategorized_text',
				display: 'Additional Details',
				control: 'textarea',
			},
			{
				key: 'uncategorized_file',
				control: 'file',
			},
		],
	},
];
