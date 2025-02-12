/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Deposit from '../deposit';

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, 'UTC' ); // Ensure UTC is used
	} ),
} ) );

describe( 'Deposit', () => {
	beforeEach( () => {
		// Mock the window.wcpaySettings property
		window.wcpaySettings.dateFormat = 'M j, Y';
		window.wcpaySettings.timeFormat = 'g:i a';
	} );

	afterEach( () => {
		// Reset the mock
		jest.clearAllMocks();
	} );

	test( 'renders with date and payout available', () => {
		const { container: link } = render(
			<Deposit dateAvailable="2020-01-07 00:00:00" depositId="po_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with date available but no payout', () => {
		const { container: link } = render(
			<Deposit dateAvailable="2020-01-07 00:00:00" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with payout but no date available', () => {
		const { container: link } = render( <Deposit depositId="po_mock" /> );
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with no date or payout available', () => {
		const { container: link } = render( <Deposit /> );
		expect( link ).toMatchSnapshot();
	} );
} );
