/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import TransactionSummaryDetails from '../';

describe( 'TransactionSummaryDetails', () => {
    test( 'correctly renders a transaction', () => {
        const transactionSummaryDetails = renderTransaction( {
			id: 'txn_38jdHA39KKA',
			/* Stripe data comes in seconds, instead of the default Date miliseconds */
			created: Date.parse( 'Sep 19, 2019, 5:24 pm' ) / 1000,
			order: {
				number: 45981,
				url: 'https://automattic.com/',
			},
			source: {
				// eslint-disable-next-line camelcase
				billing_details: {
					name: 'Customer Name',
				},
				// eslint-disable-next-line camelcase
				payment_method_details: {
					card: {
						brand: 'visa',
						last4: '4242',
					},
				},
				outcome: {
					// eslint-disable-next-line camelcase
					risk_level: 'normal',
				},
			},
		} );
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

