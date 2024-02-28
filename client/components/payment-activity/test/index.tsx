/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentActivity from '..';

declare const global: {
	wcpaySettings: {
		transactionsData: {
			tpv: number;
		};
	};
};

describe( 'PaymentActivity component', () => {
	test( 'Component renders an empty state', () => {
		global.wcpaySettings = {
			transactionsData: {
				tpv: 0,
			},
		};

		const { container, getByText } = render( <PaymentActivity /> );
		// Making sure the empty state is rendered
		getByText( 'No payments… yet!' );
	} );

	test( 'Component renders non-empty state', () => {
		global.wcpaySettings = {
			transactionsData: {
				tpv: 1000,
			},
		};

		const { container, getByText } = render( <PaymentActivity /> );
		// Should be updated with correct checks
		getByText( 'You have some money' );
	} );
} );
