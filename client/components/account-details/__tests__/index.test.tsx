/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountDetails from '../index';
import type { AccountDetailsType } from 'wcpay/types/account/account-details';

describe( 'AccountDetails', () => {
	test( 'renders error when accountDetails is null', () => {
		const accountDetails: AccountDetailsType = null;

		render( <AccountDetails accountDetails={ accountDetails } /> );

		expect(
			screen.getByText( 'Error loading account details.' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Account details' ) ).toBeInTheDocument();
	} );

	test( 'renders account details content when data is provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Active',
				background_color: 'green',
			},
			payout_status: {
				text: 'Available',
				background_color: 'green',
				icon: 'published',
			},
			banner: null,
		};

		render( <AccountDetails accountDetails={ accountDetails } /> );

		expect( screen.getByText( 'Account details' ) ).toBeInTheDocument();
		expect( screen.getByText( /"account_status"/ ) ).toBeInTheDocument();
		expect( screen.getByText( /"payout_status"/ ) ).toBeInTheDocument();
		expect( screen.getByText( /"banner": null/ ) ).toBeInTheDocument();
	} );
} );
