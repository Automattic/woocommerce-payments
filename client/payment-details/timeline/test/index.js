/* eslint-disable camelcase */
/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import PaymentDetailsTimeline from '../';
import { useTimeline } from 'data';

jest.mock( 'data', () => ( {
	useTimeline: jest.fn(),
} ) );

// Mock the Timeline component because it's not published at the time of writing this test.
// TODO: Remove this mock and update snapshots once the Timeline component is live.
jest.mock( '@woocommerce/components', () => {
	const Card = require.requireActual( '@woocommerce/components' ).Card;
	return {
		Card,
		Timeline: jest.fn( () => <div>Mock timeline</div> ),
	};
} );

describe( 'PaymentDetailsTimeline', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders loading', () => {
		useTimeline.mockReturnValue( {
			timeline: [],
			timelineError: null,
			isLoading: true,
		} );

		const { container } = render( <PaymentDetailsTimeline chargeId={ 'ch_test' } /> );

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders error', () => {
		useTimeline.mockReturnValue( {
			timeline: [],
			timelineError: new Error( 'Test error' ),
			isLoading: false,
		} );

		const { container } = render( <PaymentDetailsTimeline chargeId={ 'ch_test' } /> );

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly (with a mocked Timeline component)', () => {
		// Mock all the possible events.
		useTimeline.mockReturnValue( {
			timeline: [
				{
					amount: 8100,
					currency: 'USD',
					datetime: 1585452327,
					type: 'authorized',
				},
				{
					amount: 6200,
					currency: 'USD',
					datetime: 1585474323,
					type: 'authorization_voided',
				},
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1585541712,
					type: 'authorization_expired',
				},
				{
					amount: 5400,
					currency: 'USD',
					datetime: 1585552467,
					reason: 'card_declined',
					type: 'failed',
				},
				{
					amount: 6500,
					currency: 'USD',
					datetime: 1585585462,
					deposit: {
						arrival_date: 1585671862,
						id: 'dummy_po_5ea850b987b37',
					},
					fee: 350,
					type: 'captured',
				},
				{
					body: [
						'card_declined',
					],
					datetime: 1585552467,
					headline: 'A payment of $54.00 failed',
					icon: expect.objectContaining( { props: expect.objectContaining( { className: 'is-error', icon: 'cross' } ) } ),
				},
				{
					body: [],
					datetime: 1585552467,
					headline: 'Payment status changed to Failed',
					hideTimestamp: true,
					icon: expect.objectContaining( { props: expect.objectContaining( { icon: 'sync' } ) } ),
				},
				{
					body: [
						'Fee: $3.50',
						'Net deposit: $61.50',
					],
					datetime: 1585585462,
					headline: 'A payment of $65.00 was successfully charged',
					icon: expect.objectContaining(
						{ props: expect.objectContaining( { className: 'is-success', icon: 'checkmark' } ) }
					),
				},
				{
					body: [],
					datetime: 1585585462,
					headline: expect.objectContaining( {
						props: expect.objectContaining( {
							children: [
								'$61.50 was added to your ',
								expect.any( Object ), // Link to the deposit.
							],
						} ),
					} ),
					hideTimestamp: true,
					icon: expect.objectContaining( { props: expect.objectContaining( { icon: 'plus' } ) } ),
				},
				{
					body: [],
					datetime: 1585585462,
					headline: 'Payment status changed to Paid',
					hideTimestamp: true,
					icon: expect.objectContaining( { props: expect.objectContaining( { icon: 'sync' } ) } ),
				},
				{
					amount_refunded: 5000,
					currency: 'USD',
					datetime: 1585756107,
					deposit: null,
					type: 'partial_refund',
				},
				{
					amount_refunded: 10000,
					currency: 'USD',
					datetime: 1585830256,
					deposit: null,
					type: 'full_refund',
				},
				{
					amount: 8500,
					currency: 'USD',
					datetime: 1585628598,
					deposit: null,
					dispute_id: 'some_id',
					evidence_due_by: 1585714998,
					fee: 1500,
					reason: 'fraudulent',
					type: 'dispute_needs_response',
				},
				{
					datetime: 1585700504,
					type: 'dispute_in_review',
					user_id: 1,
				},
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1585907030,
					deposit: {
						arrival_date: 1585993430,
						id: 'dummy_po_5ea850b987be4',
					},
					fee: 1500,
					type: 'dispute_won',
				},
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1585947697,
					deposit: {
						arrival_date: 1586034097,
						id: 'dummy_po_5ea850b987c01',
					},
					fee: 1500,
					type: 'dispute_lost',
				},
			],
			timelineError: null,
			isLoading: false,
		} );

		const { container } = render( <PaymentDetailsTimeline chargeId={ 'ch_test' } /> );

		expect( container ).toMatchSnapshot();
	} );
} );
