/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import DisputedOrderNoticeHandler from '../index';
import { useCharge } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useCharge: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'DisputedOrderNoticeHandler', () => {
	const mockCharge = {
		dispute: {
			status: 'needs_response',
			reason: 'fraudulent',
			amount: 1000,
			currency: 'USD',
			evidence_details: {
				due_by: 1698500219,
			},
		},
	};

	beforeEach( () => {
		window.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
		};
		useCharge.mockReturnValue( { data: mockCharge } );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.clearAllMocks();
	} );

	test( 'renders urgent dispute notice', () => {
		const fixedDate = new Date( '2023-10-28T00:00:00Z' );
		jest.useFakeTimers();
		jest.setSystemTime( fixedDate );

		const { container } = render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);
		const disputeMessages = screen.getAllByText(
			/Please resolve the dispute on this order of/
		);
		expect( disputeMessages[ 0 ] ).toBeInTheDocument();
		expect( screen.getByRole( 'button' ) ).toHaveTextContent(
			'Respond today'
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders regular dispute notice', () => {
		const fixedDate = new Date( '2023-10-20T00:00:00Z' );
		jest.useFakeTimers();
		jest.setSystemTime( fixedDate );

		const { container } = render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);
		const disputeMessages = screen.getAllByText( /Please respond before/ );
		expect( disputeMessages[ 0 ] ).toBeInTheDocument();
		expect( screen.getByRole( 'button' ) ).toHaveTextContent(
			'Respond now'
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'does not render notice if no dispute', () => {
		useCharge.mockReturnValue( { data: {} } );
		const { container } = render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'does not render notice if dispute is not awaiting response', () => {
		mockCharge.dispute.status = 'won';
		render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);
		expect(
			screen.queryByText( /Please resolve the dispute on this order of/ )
		).not.toBeInTheDocument();
	} );
} );
