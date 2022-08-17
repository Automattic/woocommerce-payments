/** @format */
/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

const getCustomerCurrencies = () => {
	return (
		wcSettings.customerCurrencies.sort( ( a, b ) => {
			return a.label < b.label ? -1 : 1;
		} ) ?? []
	);
};

addFilter(
	'woocommerce_admin_orders_report_advanced_filters',
	'woocommerce-payments',
	( advancedFilters ) => {
		advancedFilters.filters = {
			currency: {
				labels: {
					add: __( 'Customer currency', 'woocommerce-payments' ),
					remove: __(
						'Remove customer currency filter',
						'woocommerce-payments'
					),
					rule: __(
						'Select a customer currency filter match',
						'woocommerce-payments'
					),
					title: __(
						'{{title}}Customer Currency{{/title}} {{rule /}} {{filter /}}',
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
						/* translators: Sentence fragment, logical, "Is" refers to searching for orders matching a chosen currency. */
						label: __(
							'Is',
							'customer currency',
							'woocommerce-payments'
						),
					},
					{
						value: 'is_not',
						// eslint-disable-next-line max-len
						/* translators: Sentence fragment, logical, "Is Not" refers to searching for orders not matching a chosen currency. */
						label: __(
							'Is Not',
							'customer currency',
							'woocommerce-payments'
						),
					},
				],
				input: {
					component: 'SelectControl',
					options: getCustomerCurrencies(),
				},
				allowMultiple: true,
			},
			...advancedFilters.filters,
		};

		return advancedFilters;
	}
);

addFilter(
	'woocommerce_admin_report_table',
	'woocommerce-payments',
	( tableData ) => {
		// If we don't need to or are unable to add the column, just return the table data.
		if (
			! tableData.items ||
			! tableData.items.data ||
			! tableData.items.data.length ||
			'orders' !== tableData.endpoint
		) {
			return tableData;
		}

		const updatedHeaders = [
			...tableData.headers,
			{
				isNumeric: false,
				isSortable: false,
				key: 'customer_currency',
				label: __( 'Customer currency', 'woocommerce-payments' ),
				required: false,
				screenReaderLabel: __(
					'Customer currency',
					'woocommerce-payments'
				),
			},
		];

		const updatedRows = tableData.rows.map( ( rows, index ) => {
			const item = tableData.items.data[ index ];
			const currency = item.hasOwnProperty( 'order_currency' )
				? item.order_currency
				: '';

			return [
				...rows,
				{
					display: currency,
					value: currency,
				},
			];
		} );

		tableData.rows = updatedRows;
		tableData.headers = updatedHeaders;

		return tableData;
	}
);

const addCurrencyFilters = ( filters ) => {
	return [
		{
			label: __( 'Currency', 'customer currency' ),
			staticParams: [],
			param: 'currency',
			showFilters: () => true,
			defaultValue: 'USD',
			filters: [ ...getCustomerCurrencies() ],
		},
		...filters,
	];
};

addFilter(
	'woocommerce_admin_orders_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_revenue_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_products_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_variations_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_categories_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_coupons_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);
addFilter(
	'woocommerce_admin_taxes_report_filters',
	'woocommerce-payments',
	addCurrencyFilters
);

const currencies = {
	USD: {
		code: 'USD',
		symbol: '$', // For the sake of the example.
		symbolPosition: 'left',
		thousandSeparator: ',',
		decimalSeparator: '.',
		precision: 2,
	},
	GBP: {
		code: 'GBP',
		symbol: '£',
		symbolPosition: 'left',
		thousandSeparator: ',',
		decimalSeparator: '.',
		precision: 2,
	},
};

const updateReportCurrencies = ( config, { currency } ) => {
	if ( currency && currencies[ currency ] ) {
		return currencies[ currency ];
	}
	return config;
};

addFilter(
	'woocommerce_admin_report_currency',
	'woocommerce-payments',
	updateReportCurrencies
);
