/** @format */

/**
 * Internal dependencies
 */
import { emptyFeesValue, getFeesColumnCell, getFeesColumns } from '../columns';

describe( 'Fees report columns', () => {
	test( 'returns the default visible columns and hidden settlement columns in order', () => {
		const columns = getFeesColumns();

		expect( columns.map( ( { key } ) => key ) ).toStrictEqual( [
			'date',
			'payment_method',
			'type',
			'order_id',
			'transaction_id',
			'transaction_currency',
			'amount',
			'fees',
			'deposit_date',
			'deposit_id',
		] );

		expect(
			columns.map( ( { key, label, visible } ) => ( {
				key,
				label,
				visible,
			} ) )
		).toMatchObject( [
			{ key: 'date', label: 'Date & time', visible: true },
			{ key: 'payment_method', label: 'Method', visible: true },
			{ key: 'type', label: 'Type', visible: true },
			{ key: 'order_id', label: 'Order ID', visible: true },
			{
				key: 'transaction_id',
				label: 'Transaction ID',
				visible: true,
			},
			{ key: 'transaction_currency', label: 'Currency', visible: true },
			{ key: 'amount', label: 'Gross amount', visible: true },
			{ key: 'fees', label: 'Fees total', visible: true },
			{ key: 'deposit_date', label: 'Settlement date', visible: false },
			{ key: 'deposit_id', label: 'Payout ID', visible: false },
		] );
	} );

	test( 'marks sortable and required columns for TableCard', () => {
		const columns = getFeesColumns();

		expect( columns.find( ( { key } ) => key === 'date' ) ).toMatchObject( {
			defaultSort: true,
			defaultOrder: 'desc',
			isSortable: true,
		} );
		expect(
			columns.find( ( { key } ) => key === 'payment_method' )
		).toMatchObject( {
			isSortable: false,
		} );
		expect( columns.find( ( { key } ) => key === 'type' ) ).toMatchObject( {
			isSortable: false,
		} );
		expect(
			columns.find( ( { key } ) => key === 'transaction_id' )
		).toMatchObject( {
			required: true,
		} );
		expect( columns.find( ( { key } ) => key === 'amount' ) ).toMatchObject(
			{
				isNumeric: true,
				isSortable: true,
			}
		);
		expect( columns.find( ( { key } ) => key === 'fees' ) ).toMatchObject( {
			isNumeric: true,
			isSortable: true,
		} );
	} );

	test( 'uses the placeholder for empty payout IDs', () => {
		expect(
			getFeesColumnCell(
				{
					deposit_id: null,
				},
				'deposit_id'
			)
		).toStrictEqual( {
			value: '',
			display: emptyFeesValue,
		} );
	} );
} );
