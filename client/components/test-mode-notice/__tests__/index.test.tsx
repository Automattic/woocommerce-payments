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

	describe( 'Details view', () => {
		// Every CurrentPage the switch handles, with the plural noun it resolves
		// to. documents/loans/transactions have no isDetailsView call site today,
		// but the type permits them and the switch handles them, so pin them too.
		const detailsPages: [ CurrentPage, string ][] = [
			[ 'payments', 'orders' ],
			[ 'deposits', 'payouts' ],
			[ 'disputes', 'disputes' ],
			[ 'documents', 'documents' ],
			[ 'loans', 'loans' ],
			[ 'transactions', 'orders' ],
		];

		const renderDetails = ( page: CurrentPage ) => {
			mockIsInTestMode.mockReturnValue( true );
			mockIsInDevMode.mockReturnValue( false );

			return render(
				<TestModeNotice currentPage={ page } isDetailsView={ true } />
			).container;
		};

		test.each( detailsPages )(
			'makes no claim about when the %s record was created',
			( page ) => {
				// The notice receives no order-, charge- or intent-scoped input,
				// so it cannot know the mode a record was created in. This pins
				// the shape of that claim rather than one past wording, so a
				// reworded regression is still caught.
				expect( renderDetails( page ).textContent ).not.toMatch(
					/\b(was|were)\b[^.]*\b(placed|created)\b/
				);
			}
		);

		test.each( detailsPages )(
			'states the store mode and what the %s view is scoped to',
			( page, plural ) => {
				expect( renderDetails( page ).textContent ).toBe(
					`WooPayments is in test mode, so only test ${ plural } are shown. ` +
						`To view live ${ plural }, disable test mode in WooPayments settings.`
				);
			}
		);

		test( 'dev mode still takes precedence over the details-view copy', () => {
			mockIsInTestMode.mockReturnValue( true );
			mockIsInDevMode.mockReturnValue( true );

			const { container } = render(
				<TestModeNotice currentPage="payments" isDetailsView={ true } />
			);

			expect( container.textContent ).toContain(
				'development or staging environment'
			);
		} );
	} );
} );
