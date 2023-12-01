/**
 * External dependencies
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import {
	displayType,
	sourceDevice,
	channel,
	riskLevel,
} from 'transactions/strings';

interface TransactionsFilterEntryType {
	label: string;
	value: string;
}

export interface TransactionsFilterType {
	label: string;
	param: string;
	staticParams: string[];
	showFilters: () => boolean;
	filters: TransactionsFilterEntryType[];
	defaultValue?: string;
}

const transactionTypesOptions = Object.entries( displayType )
	.map( ( [ type, label ] ) => {
		//@TODO - implement filter transactions by card reader fee
		if ( type === 'card_reader_fee' ) {
			return null;
		}
		return { label, value: type };
	} )
	.filter( function ( el ) {
		return el != null;
	} );

const loanDefinitions =
	'undefined' !== typeof wcpaySettings
		? wcpaySettings.accountLoans.loans
		: [];

const loanSelectionOptions = loanDefinitions.map( ( loanDefinition ) => {
	const loanDefinitionSplitted = loanDefinition.split( '|' );
	const loanDisplayValue = sprintf(
		'ID: %s | %s',
		loanDefinitionSplitted[ 0 ],
		'active' === loanDefinitionSplitted[ 1 ]
			? __( 'In Progress', 'woocommerce-payments' )
			: __( 'Paid in Full', 'woocommerce-payments' )
	);

	return { label: loanDisplayValue, value: loanDefinitionSplitted[ 0 ] };
}, [] );

const transactionSourceDeviceOptions = Object.entries( sourceDevice ).map(
	( [ type, label ] ) => {
		return { label, value: type };
	}
);

const transactionChannelOptions = Object.entries( channel ).map(
	( [ type, label ] ) => {
		return { label, value: type };
	}
);

const transactionRiskLevelOptions = Object.entries( riskLevel ).map(
	( [ type, label ] ) => {
		return { label, value: type };
	}
);

const transactionCustomerCounryOptions = Object.entries(
	wcSettings.countries
).map( ( [ type, label ] ) => {
	return { label, value: type };
} );

export const getFilters = (
	depositCurrencyOptions: TransactionsFilterEntryType[],
	showDepositCurrencyFilter: boolean
): [ TransactionsFilterType, TransactionsFilterType ] => {
	return [
		{
			label: __( 'Deposit currency', 'woocommerce-payments' ),
			param: 'store_currency_is',
			staticParams: [
				'paged',
				'per_page',
				'orderby',
				'order',
				'search',
				'filter',
				'type_is',
				'type_is_not',
				'date_before',
				'date_after',
				'date_between',
				'source_device_is',
				'source_device_is_not',
				'channel_is',
				'channel_is_not',
				'customer_country_is',
				'customer_country_is_not',
				'risk_level_is',
				'risk_level_is_not',
			],
			showFilters: () => showDepositCurrencyFilter,
			filters: [
				{
					label: __( 'All currencies', 'woocommerce-payments' ),
					value: '---',
				},
				...depositCurrencyOptions,
			],
			defaultValue: '---',
		},
		{
			label: __( 'Show', 'woocommerce-payments' ),
			param: 'filter',
			staticParams: [
				'paged',
				'per_page',
				'orderby',
				'order',
				'search',
				'store_currency_is',
			],
			showFilters: () => true,
			filters: [
				{
					label: __( 'All transactions', 'woocommerce-payments' ),
					value: 'all',
				},
				{
					label: __( 'Advanced filters', 'woocommerce-payments' ),
					value: 'advanced',
				},
			],
		},
	];
};

/**
 * TODO: Add an interface here for advanced filters, or adjust ESLint rules to allow using inferred type.
 */

/*eslint-disable max-len*/
export const getAdvancedFilters = (
	customerCurrencyOptions?: TransactionsFilterEntryType[]
): any => {
	// TODO: Remove this and all the checks once we drop support of WooCommerce 7.7 and below.
	const wooCommerceVersionString = getSetting( 'wcVersion' );
	const wooCommerceVersion = parseFloat( wooCommerceVersionString ); // This will parse 7.7.1 to 7.7, but it's fine for this purpose

	return {
		/** translators: A sentence describing filters for Transactions. */
		title:
			wooCommerceVersion < 7.8
				? __(
						'Transactions match {{select /}} filters',
						'woocommerce-payments'
				  )
				: __(
						'Transactions match <select /> filters',
						'woocommerce-payments'
				  ),
		filters: {
			date: {
				labels: {
					add: __( 'Date', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction date filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction date filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction date filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Date{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Date</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction date',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'before',
						label: __( 'Before', 'woocommerce-payments' ),
					},
					{
						value: 'after',
						label: __( 'After', 'woocommerce-payments' ),
					},
					{
						value: 'between',
						label: __( 'Between', 'woocommerce-payments' ),
					},
				],
				input: {
					component: 'Date',
				},
			},
			customer_currency: {
				labels: {
					add: __( 'Customer currency', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction customer currency filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction customer currency filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction customer currency filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Customer currency{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Customer currency</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a customer currency',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen presentment currency. */
						label: _x(
							'Is',
							'transaction customer currency',
							'woocommerce-payments'
						),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen presentment currency. */
						label: _x(
							'Is not',
							'transaction customer currency',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: customerCurrencyOptions,
				},
			},
			type: {
				labels: {
					add: __( 'Type', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction type filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction type filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction type filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Type{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Type</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction type',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction type. */
						label: _x(
							'Is',
							'transaction type',
							'woocommerce-payments'
						),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction type. */
						label: _x(
							'Is not',
							'transaction type',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: transactionTypesOptions,
				},
			},
			loan_id_is: {
				labels: {
					add: __( 'Loan', 'woocommerce-payments' ),
					remove: __( 'Remove loan filter', 'woocommerce-payments' ),
					rule: __( 'Select a loan', 'woocommerce-payments' ),
					/* translators: A sentence describing a Loan ID filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Loan{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Loan</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __( 'Select a loan', 'woocommerce-payments' ),
				},
				input: {
					component: 'SelectControl',
					type: 'loans',
					options: loanSelectionOptions,
				},
			},
			source_device: {
				labels: {
					add: __( 'Device Type', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction device type filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction device type filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction Device Type filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Device type{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Device type</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction device type',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction type. */
						label: _x(
							'Is',
							'Source device',
							'woocommerce-payments'
						),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction type. */
						label: _x(
							'Is not',
							'Source device',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: transactionSourceDeviceOptions,
				},
			},
			channel: {
				labels: {
					add: __( 'Channel', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction channel filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction channel filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction Channel filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Channel{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Channel</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction channel',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction channel type. */
						label: _x( 'Is', 'Channel', 'woocommerce-payments' ),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction channel type. */
						label: _x(
							'Is not',
							'Channel',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: transactionChannelOptions,
				},
			},
			customer_country: {
				labels: {
					add: __( 'Customer Country', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction customer country filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction customer country filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction customer country. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Customer country{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Customer country</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction customer country',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction customer country. */
						label: _x(
							'Is',
							'Customer Country',
							'woocommerce-payments'
						),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction customer country. */
						label: _x(
							'Is not',
							'Customer Country',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: transactionCustomerCounryOptions,
				},
			},
			risk_level: {
				labels: {
					add: __( 'Risk Level', 'woocommerce-payments' ),
					remove: __(
						'Remove transaction Risk Level filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a transaction Risk Level filter match',
						'woocommerce-payments'
					),
					/* translators: A sentence describing a Transaction Risk Level filter. */
					title:
						wooCommerceVersion < 7.8
							? __(
									'{{title}}Risk Level{{/title}} {{rule /}} {{filter /}}',
									'woocommerce-payments'
							  )
							: __(
									'<title>Risk Level</title> <rule /> <filter />',
									'woocommerce-payments'
							  ),
					filter: __(
						'Select a transaction Risk Level',
						'woocommerce-payments'
					),
				},
				rules: [
					{
						value: 'is',
						/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction risk level. */
						label: _x( 'Is', 'Risk Level', 'woocommerce-payments' ),
					},
					{
						value: 'is_not',
						/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction risk level. */
						label: _x(
							'Is not',
							'Risk Level',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: transactionRiskLevelOptions,
				},
			},
		},
	};
};
/*eslint-enable max-len*/
