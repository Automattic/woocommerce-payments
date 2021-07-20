/** @format */
/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './styles.scss';
import { useDefaultCurrency, useEnabledCurrencies } from 'data';

/**
 * List of analytics pages where currency is used.
 *
 * @return {string[]} The list of pages.
 */
const getPages = () => {
	return [
		'orders',
		'revenue',
		'products',
		'categories',
		'coupons',
		'taxes',
	];
};

addFilter(
	'woocommerce_admin_report_table',
	'woocommerce-payments',
	( tableData ) => {
		const storeDefaultCurrency = useDefaultCurrency();

		// If we don't need to or are unable to add the column, just return the table data.
		if (
			! getPages().includes( tableData.endpoint ) ||
			! tableData.items ||
			! tableData.items.data ||
			! tableData.items.data.length
		) {
			return tableData;
		}

		const updatedHeaders = [
			{
				isNumeric: false,
				isSortable: true,
				key: 'customer_currency',
				label: __( 'Customer Currency', 'woocommerce-payments' ),
				required: false,
				screenReaderLabel: __(
					'Customer Currency',
					'woocommerce-payments'
				),
			},
			...tableData.headers,
		];

		const updatedRows = tableData.rows.map( ( rows, index ) => {
			const item = tableData.items.data[ index ];
			const currency = item.order_currency;
			const defaultCurrency = item.order_default_currency
				? item.order_default_currency
				: storeDefaultCurrency;
			const exchangeRate = item.exchange_rate
				? parseFloat( item.exchange_rate )
				: 1.0;
			const shouldConvert = currency !== defaultCurrency;

			const newRows = rows.map( ( column, columnIndex ) => {
				const key = tableData.headers[ columnIndex ].key;

				if ( 'net_total' !== key || ! shouldConvert ) {
					return {
						...column,
					};
				}

				const netTotal = shouldConvert
					? item.net_total * exchangeRate
					: item.net_total;

				return {
					...column,
					display: '$' + netTotal.toFixed( 2 ),
					value: parseFloat( netTotal.toFixed( 2 ) ),
				};
			} );

			return [
				{
					display: currency,
					value: currency,
				},
				...newRows,
			];
		} );

		tableData.headers = updatedHeaders;
		tableData.rows = updatedRows;

		return tableData;
	}
);

getPages.forEach( ( page ) => {
	addFilter(
		`woocommerce_admin_${ page }_report_filters`,
		'woocommerce-payments',
		( filters ) => {
			const defaultCurrency = useDefaultCurrency();
			const enabledCurrencies = useEnabledCurrencies();

			const filterByCurrency = {
				label: 'test', // todo: set the label in data
				staticParams: [],
				param: 'currency',
				showFilters: () => true,
				defaultValue: defaultCurrency,
				filters: [ ...( enabledCurrencies || [] ) ],
			};

			return [ ...filters, filterByCurrency ];
		}
	);
} );

addFilter(
	'woocommerce_admin_persisted_queries',
	'woocommerce-payments',
	( params ) => {
		params.push( 'currency' );
		return params;
	}
);

addFilter(
	'woocmmerce_admin_report_currency',
	'woocommerce-payments',
	( config, { currency } ) => {
		// If currency is in the query string, e.g. ?currency=EUR
		if ( currency ) {
			config.code = currency;
			config.symbol = currency;
		}

		config.priceFormat = '%2$s';

		return config;
	}
);
