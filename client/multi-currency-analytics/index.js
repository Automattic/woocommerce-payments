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

addFilter(
	'woocommerce_admin_report_table',
	'woocommerce-payments',
	( tableData ) => {
		// If we don't need to or are unable to add the column, just return the table data.
		if (
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

		// const updatedItems = tableData.items.data.map( ( item ) => {
		// 	const currency = item.order_currency;
		// 	const defaultCurrency = item.order_default_currency
		// 		? item.order_default_currency
		// 		: storeDefaultCurrency;
		// 	const exchangeRate = item.exchange_rate
		// 		? parseFloat( item.exchange_rate )
		// 		: 1.0;

		// 	if ( ! item.exchange_rate || currency === defaultCurrency ) {
		// 		totalNetSales += item.net_total;
		// 		return {
		// 			...item,
		// 		};
		// 	}

		// 	const totalSales = item.total_sales * ( 1 / exchangeRate );
		// 	const netTotal = item.net_total * ( 1 / exchangeRate );

		// 	totalNetSales += parseFloat( netTotal.toFixed( 2 ) );
		// 	return {
		// 		...item,
		// 		total_sales: parseFloat( totalSales.toFixed( 2 ) ),
		// 		net_total: parseFloat( netTotal.toFixed( 2 ) ),
		// 	};
		// } );

		const updatedRows = tableData.rows.map( ( rows, index ) => {
			const item = tableData.items.data[ index ];
			const currency = item.order_currency;
			// const defaultCurrency = item.order_default_currency
			// 	? item.order_default_currency
			// 	: storeDefaultCurrency;
			// const exchangeRate = item.exchange_rate
			// 	? parseFloat( item.exchange_rate )
			// 	: 1.0;
			// const shouldConvert = currency !== defaultCurrency;

			// const newRows = rows.map( ( column, columnIndex ) => {
			// 	const key = tableData.headers[ columnIndex ].key;

			// 	if ( 'net_total' !== key || ! shouldConvert ) {
			// 		return {
			// 			...column,
			// 		};
			// 	}

			// 	// Note that the exchange rate we store is for default currency => customer currency,
			// 	// so we need to flip it and find the inverse for this conversion.
			// 	const netTotal = shouldConvert
			// 		? item.net_total * ( 1 / exchangeRate )
			// 		: item.net_total;

			// 	return {
			// 		...column,
			// 		display: '$' + netTotal.toFixed( 2 ),
			// 		value: parseFloat( netTotal.toFixed( 2 ) ),
			// 	};
			// } );

			return [
				{
					display: currency,
					value: currency,
				},
				...rows,
			];
		} );

		// const updatedSummary = tableData.summary.map( ( row ) => {
		// 	if ( 'net sales' !== row.label ) {
		// 		return {
		// 			...row,
		// 		};
		// 	}

		// 	return {
		// 		...row,
		// 		value: '$' + totalNetSales.toFixed( 2 ), // TODO: get proper currency symbol
		// 	};
		// } );

		tableData.headers = updatedHeaders;
		tableData.rows = updatedRows;
		// tableData.items.data = updatedItems;
		// tableData.summary = updatedSummary;

		return tableData;
	}
);

// addFilter(
// 	'woocommerce_admin_report_currency',
// 	'woocommerce-payments',
// 	( config, { currency } ) => {
// 		// If currency is in the query string, e.g. ?currency=EUR
// 		if ( currency ) {
// 			config.code = currency;
// 			config.symbol = currency;
// 		}

// 		config.priceFormat = '%2$s';

// 		return config;
// 	}
// );
