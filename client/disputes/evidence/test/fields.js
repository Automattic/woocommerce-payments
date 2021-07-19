/** @format */

/**
 * External dependencies
 */
import { map, flatMap } from 'lodash';

/**
 * Internal dependencies
 */
import evidenceFields from '../fields';

// Universal fields.
const general = {
	key: 'general',
	fields: [
		'product_description',
		'customer_name',
		'customer_email_address',
		'customer_signature',
		'billing_address',
		'customer_purchase_ip',
		'receipt',
		'customer_communication',
	],
};
const uncategorized = {
	key: 'uncategorized',
	fields: [ 'uncategorized_text', 'uncategorized_file' ],
};

// Dispute fields per each dispute category and product type combination.
const sectionsByReasonAndProductType = {
	credit_not_processed: {
		physical_product: [
			{
				key: 'refund_policy_info',
				fields: [
					'refund_policy',
					'refund_policy_disclosure',
					'refund_refusal_explanation',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'refund_policy_info',
				fields: [
					'refund_policy',
					'refund_policy_disclosure',
					'refund_refusal_explanation',
				],
			},
		],
		offline_service: [
			{
				key: 'refund_policy_info',
				fields: [
					'refund_policy',
					'refund_policy_disclosure',
					'refund_refusal_explanation',
				],
			},
		],
	},
	duplicate: {
		physical_product: [
			{
				key: 'duplicate_charge_info',
				fields: [
					'duplicate_charge_id',
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
					'shipping_documentation',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'duplicate_charge_info',
				fields: [
					'duplicate_charge_id',
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
				],
			},
		],
		offline_service: [
			{
				key: 'duplicate_charge_info',
				fields: [
					'duplicate_charge_id',
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
					'service_documentation',
				],
			},
		],
	},
	fraudulent: {
		physical_product: [
			{
				key: 'shipping_information',
				fields: [
					'shipping_carrier',
					'shipping_tracking_number',
					'shipping_documentation',
					'shipping_date',
					'shipping_address',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'download_and_activity_logs',
				fields: [ 'access_activity_log' ],
			},
		],
		offline_service: [
			{
				key: 'service_details',
				fields: [ 'service_date', 'service_documentation' ],
			},
		],
	},
	product_not_received: {
		physical_product: [
			{
				key: 'shipping_information',
				fields: [
					'shipping_carrier',
					'shipping_tracking_number',
					'shipping_documentation',
					'shipping_date',
					'shipping_address',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'download_and_activity_logs',
				fields: [ 'access_activity_log' ],
			},
		],
		offline_service: [
			{
				key: 'service_details',
				fields: [ 'service_date', 'service_documentation' ],
			},
		],
	},
	product_unacceptable: {
		physical_product: [
			{
				key: 'shipping_information',
				fields: [
					'shipping_carrier',
					'shipping_tracking_number',
					'shipping_documentation',
					'shipping_date',
					'shipping_address',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'download_and_activity_logs',
				fields: [ 'access_activity_log' ],
			},
		],
		offline_service: [
			{
				key: 'service_details',
				fields: [ 'service_date', 'service_documentation' ],
			},
		],
	},
	subscription_canceled: {
		physical_product: [
			{
				key: 'cancellation_policy_info',
				fields: [
					'cancellation_policy',
					'cancellation_policy_disclosure',
					'cancellation_rebuttal',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'cancellation_policy_info',
				fields: [
					'cancellation_policy',
					'cancellation_policy_disclosure',
					'cancellation_rebuttal',
				],
			},
			{
				key: 'download_and_activity_logs',
				fields: [ 'access_activity_log' ],
			},
		],
		offline_service: [
			{
				key: 'cancellation_policy_info',
				fields: [
					'cancellation_policy',
					'cancellation_policy_disclosure',
					'cancellation_rebuttal',
				],
			},
			{
				key: 'service_details',
				fields: [ 'service_date', 'service_documentation' ],
			},
		],
	},
	unrecognized: {
		physical_product: [
			{
				key: 'shipping_information',
				fields: [
					'shipping_carrier',
					'shipping_tracking_number',
					'shipping_documentation',
					'shipping_date',
					'shipping_address',
				],
			},
		],
		digital_product_or_service: [
			{
				key: 'download_and_activity_logs',
				fields: [ 'access_activity_log' ],
			},
		],
		offline_service: [
			{
				key: 'service_details',
				fields: [ 'service_date', 'service_documentation' ],
			},
		],
	},
	general: {
		physical_product: [],
		digital_product_or_service: [],
		offline_service: [],
	},
};

// Convert hierarchical structure into set of tuples accepted by test runner.
const combinations = flatMap(
	sectionsByReasonAndProductType,
	( byProductType, reason ) => {
		return map( byProductType, ( sections, productType ) => [
			reason,
			productType,
			sections,
		] );
	}
);

describe( 'Dispute evidence fields', () => {
	test( 'with no reason or product type', () => {
		expect( evidenceFields() ).toEqual( [] );
	} );

	test.each( combinations )(
		'when %s with %s',
		( reason, productType, sections ) => {
			// Distill field data structure, preserving structure and keys.
			const keys = evidenceFields( reason, productType ).map(
				( section ) => ( {
					key: section.key,
					fields: section.fields.map( ( field ) => field.key ),
				} )
			);

			// Verify conditional fields, and that universal fields are above and below.
			expect( keys ).toEqual( [ general, ...sections, uncategorized ] );
		}
	);
} );
