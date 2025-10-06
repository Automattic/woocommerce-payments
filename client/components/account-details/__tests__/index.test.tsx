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

// Mock the components that have external dependencies
jest.mock( 'wcpay/components/account-status/account-tools', () => ( {
	AccountTools: () => <div data-testid="account-tools">Account Tools</div>,
} ) );

jest.mock( 'wcpay/components/account-status/account-fees', () => ( {
	__esModule: true,
	default: () => <div data-testid="account-fees">Account Fees</div>,
} ) );

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'AccountDetails', () => {
	test( 'renders error when accountDetails is null', () => {
		const accountDetails: AccountDetailsType = null;

		render( <AccountDetails accountDetails={ accountDetails } /> );

		expect(
			screen.getByText( 'Error loading account details.' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Account details' ) ).toBeInTheDocument();
	} );

	test( 'renders account details with basic data', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Complete',
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
		expect( screen.getByText( 'Complete' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Payouts:' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Available' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'account-tools' ) ).toBeInTheDocument();
	} );

	test( 'renders banner when provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Restricted',
				background_color: 'yellow',
			},
			payout_status: {
				text: 'Disabled',
				background_color: 'red',
				icon: 'error',
			},
			banner: {
				text: 'Your account has been restricted by Stripe.',
				background_color: 'yellow',
				cta_text: 'Contact support',
				cta_link: 'https://example.com/support',
				icon: 'caution',
			},
		};

		render( <AccountDetails accountDetails={ accountDetails } /> );

		expect(
			screen.getByText( 'Your account has been restricted by Stripe.' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Contact support' ) ).toBeInTheDocument();
	} );

	test( 'renders banner without CTA when only text is provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Rejected',
				background_color: 'red',
			},
			payout_status: {
				text: 'Disabled',
				background_color: 'red',
				icon: 'error',
			},
			banner: {
				text: 'Your account has been rejected.',
				background_color: 'red',
				icon: 'caution',
			},
		};

		render( <AccountDetails accountDetails={ accountDetails } /> );

		const banner = document.querySelector(
			'.woopayments-account-details__banner'
		);
		expect( banner ).toBeInTheDocument();
		expect( banner ).toHaveTextContent( 'Your account has been rejected.' );
		// Should not render CTA when cta_text and cta_link are not provided
		expect( screen.queryByRole( 'link' ) ).not.toBeInTheDocument();
	} );

	test( 'renders payout popover when provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Complete',
				background_color: 'green',
			},
			payout_status: {
				text: 'Available in 2 days',
				background_color: 'yellow',
				icon: 'caution',
				popover: {
					text: 'Payouts are processed within 2 business days.',
					cta_text: 'Learn more',
					cta_link: 'https://example.com/payouts',
				},
			},
			banner: null,
		};

		render( <AccountDetails accountDetails={ accountDetails } /> );

		expect( screen.getByText( 'Available in 2 days' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'More information about payout status' )
		).toBeInTheDocument();
	} );

	test( 'renders edit details button when accountLink is provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Complete',
				background_color: 'green',
			},
			payout_status: {
				text: 'Available',
				background_color: 'green',
				icon: 'published',
			},
			banner: null,
		};

		render(
			<AccountDetails
				accountDetails={ accountDetails }
				accountLink="https://example.com/account"
			/>
		);

		expect( screen.getByText( 'Edit details' ) ).toBeInTheDocument();
		const editButton = screen.getByText( 'Edit details' ).closest( 'a' );
		expect( editButton ).toHaveAttribute( 'target', '_blank' );
	} );

	test( 'renders account fees when provided', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Complete',
				background_color: 'green',
			},
			payout_status: {
				text: 'Available',
				background_color: 'green',
				icon: 'published',
			},
			banner: null,
		};

		const accountFees = [ { payment_method: 'card', fee: { base: 2.9 } } ];

		render(
			<AccountDetails
				accountDetails={ accountDetails }
				accountFees={ accountFees }
			/>
		);

		expect( screen.getByTestId( 'account-fees' ) ).toBeInTheDocument();
	} );

	test( 'does not render account fees when array is empty', () => {
		const accountDetails: AccountDetailsType = {
			account_status: {
				text: 'Complete',
				background_color: 'green',
			},
			payout_status: {
				text: 'Available',
				background_color: 'green',
				icon: 'published',
			},
			banner: null,
		};

		render(
			<AccountDetails
				accountDetails={ accountDetails }
				accountFees={ [] }
			/>
		);

		expect(
			screen.queryByTestId( 'account-fees' )
		).not.toBeInTheDocument();
	} );
} );
