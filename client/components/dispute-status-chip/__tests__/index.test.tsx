/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import DisputeStatusChip from '../';

function renderDisputeStatus( status: string ) {
	const { container } = render( <DisputeStatusChip status={ status } /> );
	return container;
}

describe( 'DisputeStatusChip', () => {
	test( 'renders default appearance if status is unrecognized', () => {
		expect( renderDisputeStatus( 'mock_status' ) ).toMatchSnapshot();
	} );

	const statuses = [
		'needs_response',
		'under_review',
		'charge_refunded',
		'won',
		'lost',
		'warning_needs_response',
		'warning_under_review',
		'warning_closed',
	];

	test.each( statuses )( 'renders %s status', ( status ) => {
		expect( renderDisputeStatus( status ) ).toMatchSnapshot();
	} );

	describe( 'Klarna inquiries', () => {
		test( 'say they are awaiting Klarna instead of asking for a response', () => {
			const { container } = render(
				<DisputeStatusChip
					status="warning_needs_response"
					paymentMethod="klarna"
				/>
			);

			expect( container.textContent ).toBe( 'Inquiry: Awaiting Klarna' );
			expect( container.querySelector( '.chip-alert' ) ).not.toBeNull();
		} );

		test( 'drop the urgent styling once the resolution window has passed', () => {
			const { container } = render(
				<DisputeStatusChip
					status="warning_needs_response"
					paymentMethod="klarna"
					isPastDue
				/>
			);

			expect( container.textContent ).toBe( 'Inquiry: Awaiting Klarna' );
			expect( container.querySelector( '.chip-alert' ) ).toBeNull();
			expect( container.querySelector( '.chip-primary' ) ).not.toBeNull();
		} );

		test( 'leave card inquiries unchanged', () => {
			const { container } = render(
				<DisputeStatusChip
					status="warning_needs_response"
					paymentMethod="card"
					isPastDue
				/>
			);

			expect( container.textContent ).toBe( 'Inquiry: Response needed' );
			expect( container.querySelector( '.chip-alert' ) ).not.toBeNull();
		} );
	} );
} );
