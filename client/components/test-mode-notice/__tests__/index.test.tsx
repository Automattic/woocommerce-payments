/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

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
		devModeReason?: string;
	};
};

const devModeReason =
	'Development mode is on because this site is set up as a staging site.';

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

	test( 'directs development-mode Overview users to the live store', () => {
		mockIsInTestMode.mockReturnValue( true );
		mockIsInDevMode.mockReturnValue( true );
		global.wcpaySettings.devModeReason = devModeReason;

		const { container } = render(
			<TestModeNotice currentPage="overview" />
		);

		expect( container ).toHaveTextContent(
			'Your store is in development mode. WooPayments only works in test mode here, so all payments are simulated. ' +
				'Development mode is on because this site is set up as a staging site. To accept real payments, use your live store. Learn more'
		);
		expect(
			screen.getByRole( 'link', { name: /Learn more/ } )
		).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
		);
	} );

	test.each( [
		[ 'payments', 'transactions' ],
		[ 'deposits', 'payouts' ],
		[ 'disputes', 'disputes' ],
		[ 'documents', 'documents' ],
		[ 'loans', 'loans' ],
		[ 'transactions', 'transactions' ],
	] as [ CurrentPage, string ][] )(
		'names the test %s and explains development mode on list pages',
		( page, plural ) => {
			mockIsInTestMode.mockReturnValue( true );
			mockIsInDevMode.mockReturnValue( true );
			global.wcpaySettings.devModeReason = devModeReason;

			const { container } = render(
				<TestModeNotice currentPage={ page } />
			);

			expect( container ).toHaveTextContent(
				`Viewing test ${ plural }. Your store is in development mode. WooPayments only works in test mode here, so all payments are simulated. ` +
					'Development mode is on because this site is set up as a staging site. To accept real payments, use your live store. Learn more'
			);
			expect(
				screen.getByRole( 'link', { name: /Learn more/ } )
			).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
			);
		}
	);

	test( 'Does not show dev mode explanation when dev mode is not active', () => {
		mockIsInTestMode.mockReturnValue( true );
		mockIsInDevMode.mockReturnValue( false );

		const { container } = render(
			<TestModeNotice currentPage="overview" />
		);

		expect( container.textContent ).not.toContain( 'development mode' );
	} );

	describe( 'Details view', () => {
		test.each( [
			[ 'payments', 'transactions' ],
			[ 'deposits', 'payouts' ],
			[ 'disputes', 'disputes' ],
			[ 'documents', 'documents' ],
			[ 'loans', 'loans' ],
			[ 'transactions', 'transactions' ],
		] as [ CurrentPage, string ][] )(
			'states the store mode and what the %s view is scoped to',
			( page, plural ) => {
				mockIsInTestMode.mockReturnValue( true );
				mockIsInDevMode.mockReturnValue( false );

				const { container } = render(
					<TestModeNotice
						currentPage={ page }
						isDetailsView={ true }
					/>
				);

				expect( container.textContent ).toBe(
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
				'Viewing test transactions. Your store is in development mode.'
			);
		} );
	} );
} );
