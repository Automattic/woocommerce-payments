/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { ReportsHeader } from '../header';

const requestReportExport = jest.fn();
const createNotice = jest.fn();

jest.mock( 'wcpay/hooks/use-report-export', () => ( {
	useReportExport: () => ( {
		requestReportExport,
		isExportInProgress: false,
	} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { createNotice } ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => ( {
		tab: 'fees',
		payment_method_type: 'card',
	} ),
} ) );

declare const global: {
	wcpaySettings?: { currentUserEmail: string };
};

describe( 'ReportsHeader', () => {
	beforeEach( () => {
		requestReportExport.mockClear();
		createNotice.mockClear();
		global.wcpaySettings = {
			...( global.wcpaySettings ?? {} ),
			currentUserEmail: 'merchant@example.com',
		};
	} );

	it( 'does not render the Export button when the Balance tab is active', () => {
		render( <ReportsHeader activeTab="balance" /> );

		expect(
			screen.queryByRole( 'button', { name: /export/i } )
		).not.toBeInTheDocument();
	} );

	it( 'renders the Export button when the Fees tab is active', () => {
		render( <ReportsHeader activeTab="fees" /> );

		expect(
			screen.getByRole( 'button', { name: /export/i } )
		).toBeInTheDocument();
	} );

	it( 'requests an export with Fees download URLs on click', () => {
		render( <ReportsHeader activeTab="fees" /> );

		fireEvent.click( screen.getByRole( 'button', { name: /export/i } ) );

		expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		const args = requestReportExport.mock.calls[ 0 ][ 0 ];
		expect( args.exportRequestURL ).toContain(
			'/wc/v3/payments/reports/fees/download'
		);
		expect( args.exportRequestURL ).toContain(
			'user_email=merchant%40example.com'
		);
		expect( args.exportRequestURL ).toContain( 'payment_method_type=card' );
		expect( args.exportRequestURL ).toContain( 'locale=en_US' );
		expect( args.exportFileAvailabilityEndpoint ).toBe(
			'/wc/v3/payments/reports/fees/download'
		);
		expect( args.userEmail ).toBe( 'merchant@example.com' );
	} );

	it( 'shows a success notice on click', () => {
		render( <ReportsHeader activeTab="fees" /> );

		fireEvent.click( screen.getByRole( 'button', { name: /export/i } ) );

		expect( createNotice ).toHaveBeenCalledWith(
			'success',
			expect.stringContaining( 'merchant@example.com' )
		);
	} );
} );
