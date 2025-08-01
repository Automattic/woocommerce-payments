/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SetupLivePaymentsModal from '..';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

// Mock the getAdminUrl utility to return an absolute URL
jest.mock( 'utils', () => ( {
	getAdminUrl: jest.fn(
		( args: Record< string, any > ) =>
			`http://localhost/wp-admin/admin.php?${ new URLSearchParams(
				args
			).toString() }`
	),
} ) );

declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

describe( 'Setup Live Payments Modal', () => {
	global.wcpaySettings = {
		connectUrl: 'https://wcpay.test/connect',
	};

	it( 'modal is open by default', () => {
		render(
			<SetupLivePaymentsModal
				from="bogus"
				source="bogus-again"
				onClose={ () => jest.fn() }
			/>
		);

		expect(
			screen.queryByText(
				"Before continuing, please make sure that you're aware of the following:"
			)
		).toBeInTheDocument();
	} );

	it( 'calls `handleSetup` when setup button is clicked', async () => {
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render(
			<SetupLivePaymentsModal
				from="bogus"
				source="bogus-again"
				onClose={ () => jest.fn() }
			/>
		);

		await user.click(
			screen.getByRole( 'button', {
				name: 'Activate payments',
			} )
		);

		expect( window.location.href ).toBe(
			`http://localhost/wp-admin/admin.php?page=wc-settings&tab=checkout&path=%2Fwoopayments%2Fonboarding&source=wcpay-setup-live-payments&from=bogus`
		);
	} );
} );
