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

		const addCurrencyColumn = 'orders' === tableData.endpoint;

		if ( addCurrencyColumn ) {
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

			tableData.headers = updatedHeaders;
		}

		const updatedRows = tableData.rows.map( ( rows, index ) => {
			const item = tableData.items.data[ index ];
			const modifiedRows = rows.map( ( column, columnIndex ) => {
				const columnsToFormat = [ 'net_revenue' ];
				const key = tableData.headers[ columnIndex ].key;

				let returnVal = { ...column };

				columnsToFormat.forEach( ( colKey ) => {
					if ( colKey === key ) {
						returnVal = {
							...column,
							display: item[ key ] * 100, // TODO: Show the currency symbol here.
							value: item[ key ].toFixed( 2 ),
						};
					}
				} );

				return returnVal;
			} );

			if ( addCurrencyColumn ) {
				const currency = item.order_currency;

				return [
					{
						display: currency,
						value: currency,
					},
					...modifiedRows,
				];
			}

			return [ ...modifiedRows ];
		} );

		tableData.rows = updatedRows;

		return tableData;
	}
);
