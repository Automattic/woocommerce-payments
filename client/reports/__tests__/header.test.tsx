/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

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
		// Plain local-time strings match how date filters land in `getQuery()`
		// from the date-range picker. The forced America/New_York TZ in
		// jest-global-setup makes the start/end-of-day → UTC math deterministic.
		date_after: '2026-01-15 12:00:00',
		date_before: '2026-01-15 12:00:00',
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

	it( 'requests an export with Fees download URLs on click', async () => {
		render( <ReportsHeader activeTab="fees" /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /export/i } )
		);

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
		// Date filters from the URL query must reach the export URL —
		// otherwise the file would not match what the merchant sees on screen.
		// EST → UTC: start-of-day 00:00 EST = 05:00 UTC; end-of-day 23:59:59
		// EST = 04:59:59 UTC the next day.
		expect( args.exportRequestURL ).toContain(
			'date_after=2026-01-15%2005%3A00%3A00'
		);
		expect( args.exportRequestURL ).toContain(
			'date_before=2026-01-16%2004%3A59%3A59'
		);
		expect( args.exportFileAvailabilityEndpoint ).toBe(
			'/wc/v3/payments/reports/fees/download'
		);
		expect( args.userEmail ).toBe( 'merchant@example.com' );
	} );

	it( 'shows a success notice on click', async () => {
		render( <ReportsHeader activeTab="fees" /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /export/i } )
		);

		expect( createNotice ).toHaveBeenCalledWith(
			'success',
			expect.stringContaining( 'merchant@example.com' )
		);
	} );
} );
