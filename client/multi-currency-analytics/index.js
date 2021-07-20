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
import { useDefaultCurrency } from 'data';

addFilter(
	'woocommerce_admin_report_table',
	'woocommerce-payments',
	( tableData ) => {
		const storeDefaultCurrency = useDefaultCurrency();

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

				// Note that the exchange rate we store is for default currency => customer currency,
				// so we need to flip it and find the inverse for this conversion.
				const netTotal = shouldConvert
					? item.net_total * ( 1 / exchangeRate )
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

addFilter(
	'woocommerce_admin_report_currency',
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
