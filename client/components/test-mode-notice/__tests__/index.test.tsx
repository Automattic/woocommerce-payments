/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { isInDevMode, isInTestMode } from 'utils';
import { TestModeNotice } from '..';

declare const global: {
	wcSettings: { countries: Record< string, string > };
	wcpaySettings: {
		accountStatus: {
			detailsSubmitted: boolean;
		};
	};
};

jest.mock( 'utils', () => ( {
	isInDevMode: jest.fn(),
	isInTestMode: jest.fn(),
	getPaymentSettingsUrl: jest.fn().mockReturnValue( 'https://example.com/' ),
} ) );

const mockIsInTestMode = isInTestMode as jest.MockedFunction<
	typeof isInTestMode
>;
const mockIsInDevMode = isInDevMode as jest.MockedFunction<
	typeof isInDevMode
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
		mockIsInDevMode.mockReturnValue( false );

		const { container: testModeNotice } = render(
			<TestModeNotice currentPage={ page } />
		);

		expect( testModeNotice ).toMatchSnapshot();
	} );

	test.each( pages )( 'Returns empty div if not in test mode', ( page ) => {
		mockIsInTestMode.mockReturnValue( false );
		mockIsInDevMode.mockReturnValue( false );

		const { container: testModeNotice } = render(
			<TestModeNotice currentPage={ page } />
		);

		expect( testModeNotice ).toMatchSnapshot();
	} );

	test( 'Shows dev mode explanation on overview page when dev mode forces test', () => {
		mockIsInTestMode.mockReturnValue( true );
		mockIsInDevMode.mockReturnValue( true );

		const { container } = render(
			<TestModeNotice currentPage="overview" />
		);

		expect( container.textContent ).toContain(
			'development or staging environment'
		);
	} );

	test( 'Shows dev mode explanation on list pages when dev mode forces test', () => {
		mockIsInTestMode.mockReturnValue( true );
		mockIsInDevMode.mockReturnValue( true );

		const { container } = render(
			<TestModeNotice currentPage="transactions" />
		);

		expect( container.textContent ).toContain(
			'development or staging environment'
		);
	} );

	test( 'Does not show dev mode explanation when dev mode is not active', () => {
		mockIsInTestMode.mockReturnValue( true );
		mockIsInDevMode.mockReturnValue( false );

		const { container } = render(
			<TestModeNotice currentPage="overview" />
		);

		expect( container.textContent ).not.toContain(
			'development or staging environment'
		);
	} );
} );
