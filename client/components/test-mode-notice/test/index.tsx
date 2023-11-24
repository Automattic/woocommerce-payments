/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { isInTestMode } from 'utils';
import { TestModeNotice } from '../index';

declare const global: {
	wcSettings: { countries: Record< string, string > };
	wcpaySettings: {
		accountStatus: {
			detailsSubmitted: boolean;
		};
	};
};

jest.mock( 'utils', () => ( {
	isInTestMode: jest.fn(),
	getPaymentSettingsUrl: jest.fn().mockReturnValue( 'https://example.com/' ),
} ) );

const mockIsInTestMode = isInTestMode as jest.MockedFunction<
	typeof isInTestMode
>;

type CurrentPage =
	| 'overview'
	| 'documents'
	| 'deposits'
	| 'disputes'
	| 'loans'
	| 'payments'
	| 'transactions';

describe( 'Test mode notification', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				detailsSubmitted: true,
			},
		};
	} );

	const pages: CurrentPage[] = [
		'overview',
		'documents',
		'deposits',
		'disputes',
		'loans',
		'payments',
		'transactions',
	];

	test.each( pages )( 'Returns valid component for %s page', ( page ) => {
		mockIsInTestMode.mockReturnValue( true );

		const { container: testModeNotice } = render(
			<TestModeNotice currentPage={ page } />
		);

		expect( testModeNotice ).toMatchSnapshot();
	} );

	test.each( pages )( 'Returns empty div if not in test mode', ( page ) => {
		mockIsInTestMode.mockReturnValue( false );

		const { container: testModeNotice } = render(
			<TestModeNotice currentPage={ page } />
		);

		expect( testModeNotice ).toMatchSnapshot();
	} );
} );
