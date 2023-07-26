/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { includes } from 'lodash';

/* eslint-disable max-len */
const sections = [
	{
		key: 'general',
		title: __( 'General evidence', 'woocommerce-payments' ),
		description: __(
			'Provide general evidence about the customer and the order.',
			'woocommerce-payments'
		),
		fields: [
			{
				key: 'product_description',
				label: __( 'Product description', 'woocommerce-payments' ),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					'A description of the product or service and any relevant details on how this was presented to the customer at the time of purchase.',
					'woocommerce-payments'
				),
			},
			{
				key: 'customer_name',
				label: __( 'Customer name', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'customer_email_address',
				label: __( 'Customer email', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'customer_signature',
				label: __( 'Customer signature', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					"A relevant document or contract showing the customer's signature (if available).",
					'woocommerce-payments'
				),
			},
			{
				key: 'billing_address',
				label: __( 'Customer billing address', 'woocommerce-payments' ),
				type: 'textarea',
			},
			{
				key: 'customer_purchase_ip',
				label: __( 'Customer IP address', 'woocommerce-payments' ),
				type: 'text',
			},
			{
				key: 'receipt',
				label: __( 'Receipt', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Any receipt or message sent to the customer notifying them of the charge. This field will be automatically filled with a Stripe generated email receipt if any such receipt was sent.',
					'woocommerce-payments'
				),
			},
			{
				key: 'customer_communication',
				label: __( 'Customer communication', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Any communication with the customer that you feel is relevant to your case (e.g. emails proving that they received the product or service, or demonstrating their use of or satisfaction with the product or service).',
					'woocommerce-payments'
				),
			},
		],
	},
	{
		key: 'refund_policy_info',
		title: __( 'Refund policy info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'refund_policy',
				label: __( 'Refund policy', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Your refund policy, as shown or provided to the customer.',
					'woocommerce-payments'
				),
			},
			{
				key: 'refund_policy_disclosure',
				label: __( 'Refund policy disclosure', 'woocommerce-payments' ),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					'An explanation of how and when the customer was shown or provided your refund policy prior to purchase.',
					'woocommerce-payments'
				),
			},
			{
				key: 'refund_refusal_explanation',
				label: __(
					'Refund refusal explanation',
					'woocommerce-payments'
				),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					'Your explanation for why the customer is not entitled to a refund.',
					'woocommerce-payments'
				),
			},
		],
		reason: 'credit_not_processed',
	},
	{
		key: 'duplicate_charge_info',
		title: __( 'Duplicate charge info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'duplicate_charge_id',
				label: __( 'Duplicate charge ID', 'woocommerce-payments' ),
				type: 'text',
				description: __(
					'The charge ID for the previous payment that appears to be a duplicate of the one that is disputed.',
					'woocommerce-payments'
				),
			},
			{
				key: 'duplicate_charge_explanation',
				label: __(
					'Explanation of duplicate charge',
					'woocommerce-payments'
				),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					'An explanation of the difference between the disputed payment and the prior one that appears to be a duplicate.',
					'woocommerce-payments'
				),
			},
			{
				key: 'duplicate_charge_documentation',
				label: __(
					'Duplicate charge documentation',
					'woocommerce-payments'
				),
				type: 'file',
				description: __(
					'Upload documentation for the prior payment that can uniquely identify it, such as a separate receipt. This document should be paired with a similar document from the disputed payment that proves the two are separate. This should also include a separate shipping label or receipt for the other payment. If multiple products were shipped together, provide a packing list that shows each purchase.',
					'woocommerce-payments'
				),
			},
			{
				key: 'shipping_documentation',
				label: __( 'Shipping documentation', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'A shipping label or receipt for the disputed payment.',
					'woocommerce-payments'
				),
				denormalized: true,
				productType: 'physical_product',
			},
			{
				key: 'service_documentation',
				label: __( 'Service documentation', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'A copy of a service agreement or documentation for the disputed payment.',
					'woocommerce-payments'
				),
				denormalized: true,
				productType: 'offline_service',
			},
		],
		reason: 'duplicate',
	},
	{
		key: 'shipping_information',
		title: __( 'Shipping information', 'woocommerce-payments' ),
		fields: [
			{
				key: 'shipping_carrier',
				label: __( 'Shipping carrier', 'woocommerce-payments' ),
				type: 'text',
				description: __(
					'The delivery service that shipped a physical product, such as Fedex, UPS, USPS, etc. If multiple carriers were used for this purchase, please separate them with commas.',
					'woocommerce-payments'
				),
			},
			{
				key: 'shipping_tracking_number',
				label: __( 'Tracking number', 'woocommerce-payments' ),
				type: 'text',
				description: __(
					'The tracking number (if available) for a physical product, obtained from the delivery service. If multiple tracking numbers were generated for this purchase, please separate them with commas. When we compile your evidence into a single document, these tracking numbers will be expanded to include detailed delivery information from the carrier.',
					'woocommerce-payments'
				),
			},
			{
				key: 'shipping_documentation',
				label: __( 'Proof of shipping', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Provide documentation as proof that a product was shipped to the cardholder at the same address the cardholder provided to you. This could include a copy of the shipment receipt or label, and show the full shipping address of the cardholder, if possible.',
					'woocommerce-payments'
				),
			},
			{
				key: 'shipping_date',
				label: __( 'Date of shipment', 'woocommerce-payments' ),
				type: 'date',
				description: __(
					'The date on which a physical product began its route to the shipping address. This date should be prior to the date of the dispute.',
					'woocommerce-payments'
				),
			},
			{
				key: 'shipping_address',
				label: __( 'Shipping address', 'woocommerce-payments' ),
				type: 'textarea',
				description: __(
					'The address to which a physical product was shipped. The shipping address must match a billing address verified with AVS. (A signature is not required as evidence of delivery).',
					'woocommerce-payments'
				),
			},
		],
		reason: [
			'fraudulent',
			'product_not_received',
			'product_unacceptable',
			'unrecognized',
		],
		productType: 'physical_product',
	},
	{
		key: 'cancellation_policy_info',
		title: __( 'Cancellation policy info', 'woocommerce-payments' ),
		fields: [
			{
				key: 'cancellation_policy',
				label: __( 'Cancellation policy', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Your subscription cancellation policy, as shown to the customer.',
					'woocommerce-payments'
				),
			},
			{
				key: 'cancellation_policy_disclosure',
				label: __(
					'Cancellation policy disclosure',
					'woocommerce-payments'
				),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					'An explanation of how and when the customer was shown your cancellation policy prior to purchase.',
					'woocommerce-payments'
				),
			},
			{
				key: 'cancellation_rebuttal',
				label: __( 'Cancellation rebuttal', 'woocommerce-payments' ),
				maxLength: 20000,
				type: 'textarea',
				description: __(
					"A justification for why the customer's subscription was not canceled.",
					'woocommerce-payments'
				),
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
				description: [
					__(
						'Provide at least two of the following pieces of information:',
						'woocommerce-payments'
					),
					__(
						"• Customer's IP address and their device's geographical location at the time of purchase",
						'woocommerce-payments'
					),
					__(
						'• Device ID and name of the device',
						'woocommerce-payments'
					),
					__(
						'• Customer name and email address linked to their customer profile',
						'woocommerce-payments'
					),
					__(
						'• Evidence that the customer logged into their account for your business before the transaction date',
						'woocommerce-payments'
					),
					__(
						'• Evidence that your website or app was accessed by the cardholder for purchase or services on or after the transaction date',
						'woocommerce-payments'
					),
					__(
						'• Evidence that the same device and card used in the disputed payment was used in a previous payment that was not disputed',
						'woocommerce-payments'
					),
				],
			},
		],
		reason: [ 'fraudulent', 'product_not_received' ],
		productType: 'digital_product_or_service',
	},
	{
		key: 'download_and_activity_logs',
		title: __( 'Download and activity logs', 'woocommerce-payments' ),
		fields: [
			{
				key: 'access_activity_log',
				type: 'file',
				description: __(
					'Any server or activity logs showing proof that the cardholder accessed or downloaded the purchased digital product. This information should include IP addresses, corresponding timestamps, and any detailed recorded activity.',
					'woocommerce-payments'
				),
			},
		],
		reason: [
			'product_unacceptable',
			'subscription_canceled',
			'unrecognized',
		],
		productType: 'digital_product_or_service',
		denormalized: true,
	},
	{
		key: 'service_details',
		title: __( 'Service details', 'woocommerce-payments' ),
		fields: [
			{
				key: 'service_date',
				label: __( 'Service date', 'woocommerce-payments' ),
				type: 'date',
				description: __(
					'The date on which the cardholder received or began receiving the purchased service.',
					'woocommerce-payments'
				),
			},
			{
				key: 'service_documentation',
				label: __( 'Proof of service', 'woocommerce-payments' ),
				type: 'file',
				description: __(
					'Documentation showing proof that a service was provided to the cardholder. This could include a copy of a signed contract, work order, or other form of written agreement.',
					'woocommerce-payments'
				),
			},
		],
		reason: [
			'fraudulent',
			'product_not_received',
			'product_unacceptable',
			'subscription_canceled',
			'unrecognized',
		],
		productType: 'offline_service',
	},
	{
		key: 'uncategorized',
		title: __( 'Additional details', 'woocommerce-payments' ),
		description: __(
			"Provide any extra evidence or statements you'd like the bank to see, either as text or by uploading a document.",
			'woocommerce-payments'
		),
		fields: [
			{
				key: 'uncategorized_text',
				label: __( 'Additional details', 'woocommerce-payments' ),
				maxLength: 20000,
				type: 'textarea',
			},
			{
				key: 'uncategorized_file',
				label: __( 'Additional document', 'woocommerce-payments' ),
				type: 'file',
			},
		],
	},
];
/* eslint-enable max-len */

/**
 * Return evidence fields that pertain to given reason and productType.
 *
 * Sections can optionally specify 'reason' as a string or array of strings, and/or 'productType'.
 * Fields can optionally specify 'productType'.
 *
 * Sections and fields can specify denormalized: true to be omitted when all fields are shown.
 *
 * @param {string} reason      Dispute reason for which to present fields.
 * @param {string} productType Product type for which to present fields.
 *
 * @return {Array} Sections of fields.
 */
export default ( reason, productType ) => {
	if ( ! reason || ! productType ) {
		return [];
	}

	if ( 'multiple' === productType ) {
		return sections
			.map( ( section ) => {
				if ( section.denormalized ) {
					return null;
				}

				return {
					...section,
					fields: section.fields.filter(
						( field ) => ! field.denormalized
					),
				};
			} )
			.filter( Boolean );
	}

	return sections
		.map( ( section ) => {
			const reasonMismatch =
				section.reason && ! includes( section.reason, reason );
			const productTypeMismatch =
				section.productType && section.productType !== productType;
			if ( reasonMismatch || productTypeMismatch ) {
				return null;
			}

			const fields = section.fields.filter( ( field ) => {
				return ! field.productType || field.productType === productType;
			} );

			return { ...section, fields };
		} )
		.filter( Boolean );
};
