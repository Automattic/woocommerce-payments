/** @format */
/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

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
				isSortable: true,
				key: 'customer_currency',
				label: __( 'Customer Currency', 'woocommerce-payments' ),
				required: false,
				screenReaderLabel: __(
					'Customer Currency',
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
