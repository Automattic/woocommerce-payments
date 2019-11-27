/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import TransactionSummaryDetails from '../';

const getBaseTransaction = ( transaction = {} ) => ( {
	...{
		id: 'txn_38jdHA39KKA',
		amount: 1500,
		fee: 74,
		net: 1426,
		currency: 'usd',
		type: 'charge',
	},
	...transaction,
} );

describe( 'TransactionSummaryDetails', () => {
	test( 'correctly renders a transaction', () => {
		const transactionSummaryDetails = renderTransaction( getBaseTransaction() );
		expect( transactionSummaryDetails ).toMatchSnapshot();
	} );

	test( 'renders refund information for a transaction', () => {
		// eslint-disable-next-line camelcase
		const transactionSummaryDetails = renderTransaction( getBaseTransaction( { source: { refunded: false, amount_refunded: 1200 } } ) );
		expect( transactionSummaryDetails ).toMatchSnapshot();
	} );

	test( 'renders defaults if transaction object is empty', () => {
		const transactionSummaryDetails = renderTransaction( {} );
		expect( transactionSummaryDetails ).toMatchSnapshot();
	} );

	function renderTransaction( transaction ) {
		return shallow( <TransactionSummaryDetails transaction={ transaction } /> );
	}
} );

