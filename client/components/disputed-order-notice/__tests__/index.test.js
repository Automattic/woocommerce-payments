/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import DisputedOrderNoticeHandler from '..';
import { useCharge } from 'wcpay/data/charges';

jest.mock( 'wcpay/data/charges', () => ( {
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
			dateFormat: 'M j, Y',
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

	test( 'consolidates multiple awaiting-response disputes into one notice, using the summed amount and earliest due date', () => {
		const fixedDate = new Date( '2023-10-20T00:00:00Z' );
		jest.useFakeTimers();
		jest.setSystemTime( fixedDate );

		useCharge.mockReturnValue( {
			data: {
				disputes: [
					{
						id: 'dp_1',
						status: 'needs_response',
						reason: 'fraudulent',
						amount: 1000,
						currency: 'USD',
						// Oct 30, 2023 — the later deadline.
						evidence_details: { due_by: 1698672000 },
					},
					{
						id: 'dp_2',
						status: 'needs_response',
						reason: 'product_not_received',
						amount: 1550,
						currency: 'USD',
						// Oct 28, 2023 — the earliest deadline, so the one shown.
						evidence_details: { due_by: 1698500219 },
					},
				],
			},
		} );

		render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);

		expect(
			screen.getByText(
				'This order has 2 payment disputes totaling $25.50.'
			)
		).toBeInTheDocument();

		// The earliest of the two deadlines is surfaced.
		expect(
			screen.getAllByText( /Please respond before Oct 28, 2023/ ).length
		).toBeGreaterThan( 0 );

		// A single consolidated notice, not one per dispute.
		expect( screen.getAllByRole( 'button' ) ).toHaveLength( 1 );
		expect( screen.getByRole( 'button' ) ).toHaveTextContent(
			'Respond now'
		);
	} );

	test( 'calls the consolidated notice inquiries when every awaiting item is an inquiry', () => {
		const fixedDate = new Date( '2023-10-20T00:00:00Z' );
		jest.useFakeTimers();
		jest.setSystemTime( fixedDate );

		useCharge.mockReturnValue( {
			data: {
				disputes: [
					{
						id: 'dp_1',
						status: 'warning_needs_response',
						reason: 'fraudulent',
						amount: 1000,
						currency: 'USD',
						evidence_details: { due_by: 1698672000 },
					},
					{
						id: 'dp_2',
						status: 'warning_needs_response',
						reason: 'product_not_received',
						amount: 1550,
						currency: 'USD',
						evidence_details: { due_by: 1698500219 },
					},
				],
			},
		} );

		render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ jest.fn() }
			/>
		);

		expect(
			screen.getByText(
				'This order has 2 payment inquiries totaling $25.50.'
			)
		).toBeInTheDocument();
	} );

	test( 'disables order refunds when any dispute is not refundable, even behind a refundable one', () => {
		const onDisableOrderRefund = jest.fn();
		useCharge.mockReturnValue( {
			data: {
				disputes: [
					{
						id: 'dp_1',
						status: 'won',
						reason: 'fraudulent',
						amount: 1000,
						currency: 'USD',
						evidence_details: { due_by: 1698500219 },
					},
					{
						id: 'dp_2',
						status: 'needs_response',
						reason: 'fraudulent',
						amount: 1550,
						currency: 'USD',
						evidence_details: { due_by: 1698500219 },
					},
				],
			},
		} );

		render(
			<DisputedOrderNoticeHandler
				chargeId="ch_123"
				onDisableOrderRefund={ onDisableOrderRefund }
			/>
		);

		expect( onDisableOrderRefund ).toHaveBeenCalledWith( 'needs_response' );
	} );
} );
