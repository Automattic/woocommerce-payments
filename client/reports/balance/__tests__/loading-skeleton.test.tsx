/** @format */

import React from 'react';
import { render, screen } from '@testing-library/react';

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: (
		amount: number,
		currency: string,
		skipSymbol?: boolean
	) =>
		skipSymbol ? `${ amount } ${ currency }` : `${ currency } ${ amount }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) =>
		`formatted ${ value.slice( 0, 10 ) }`,
} ) );

beforeAll( () => {
	(
		window as unknown as {
			wcpaySettings: { accountDefaultCurrency: string };
		}
	 ).wcpaySettings = { accountDefaultCurrency: 'usd' };
} );

import { BalanceLoadingSkeleton } from '../loading-skeleton';

describe( 'BalanceLoadingSkeleton', () => {
	it( 'renders the DataViews summary marked aria-hidden so screen reader users skip it', () => {
		const { container } = render( <BalanceLoadingSkeleton /> );

		const view = container.querySelector( '.wcpay-reports-balance-dv' );
		expect( view ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	it( 'omits the interactive date filter from the preview', () => {
		const { container } = render( <BalanceLoadingSkeleton /> );

		// The preview renders only the rows Layout (the table; aria-hidden,
		// so role queries need `hidden`) — no DataViews Filters shell, and
		// nothing behind the blur is focusable.
		expect(
			screen.getByRole( 'table', { hidden: true } )
		).toBeInTheDocument();
		expect(
			container.querySelectorAll( 'button, input, select, a' )
		).toHaveLength( 0 );
	} );

	it( 'exposes a status heading announcing the loading text', () => {
		render( <BalanceLoadingSkeleton /> );

		const status = screen.getByRole( 'status' );
		expect( status ).toHaveTextContent( /loading balance report/i );
	} );

	it( 'forwards headingRef and headingTabIndex to the status heading', () => {
		const ref = React.createRef< HTMLHeadingElement >();
		render(
			<BalanceLoadingSkeleton headingRef={ ref } headingTabIndex={ -1 } />
		);

		expect( ref.current ).not.toBeNull();
		expect( ref.current ).toHaveAttribute( 'tabIndex', '-1' );
	} );

	it( 'renders a shimmer overlay element', () => {
		const { container } = render( <BalanceLoadingSkeleton /> );
		expect(
			container.querySelector(
				'.wcpay-reports-balance__skeleton-shimmer'
			)
		).toBeInTheDocument();
	} );
} );
