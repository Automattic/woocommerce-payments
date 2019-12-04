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
	...transaction,
} );

describe( 'TransactionSummaryDetails', () => {
	test( 'correctly renders a transaction', () => {
		const transactionSummaryDetails = renderTransaction( getBaseTransaction() );
		expect( transactionSummaryDetails ).toMatchSnapshot();
	} );

	function renderTransaction( transaction ) {
		return shallow( <TransactionSummaryDetails transaction={ transaction } /> );
	}
} );

