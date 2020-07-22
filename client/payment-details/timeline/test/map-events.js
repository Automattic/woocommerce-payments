/* eslint-disable camelcase */
/** @format */
/**
 * Internal dependencies
 */
import mapTimelineEvents from '../map-events';

describe( 'mapTimelineEvents', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'handles falsey values', () => {
		expect( mapTimelineEvents( null ) ).toStrictEqual( [] );
	} );

	test( 'formats authorized events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 7900,
					currency: 'USD',
					datetime: 1585589596,
					type: 'authorized',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats authorization_voided events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 5900,
					currency: 'USD',
					datetime: 1585652279,
					type: 'authorization_voided',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats authorization_expired events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 8600,
					currency: 'USD',
					datetime: 1585691920,
					type: 'authorization_expired',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats card_declined events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 7700,
					currency: 'USD',
					datetime: 1585712113,
					reason: 'card_declined',
					type: 'failed',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats captured events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 6300,
					currency: 'USD',
					datetime: 1585751874,
					deposit: {
						arrival_date: 1585838274,
						id: 'dummy_po_5eaada696b281',
					},
					fee: 350,
					type: 'captured',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_needs_response events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 9500,
					currency: 'USD',
					datetime: 1585793174,
					deposit: null,
					dispute_id: 'some_id',
					evidence_due_by: 1585879574,
					fee: 1500,
					reason: 'fraudulent',
					type: 'dispute_needs_response',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_in_review events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585859207,
					type: 'dispute_in_review',
					user_id: 1,
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats partial_refund events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount_refunded: 5000,
					currency: 'USD',
					datetime: 1585940281,
					deposit: null,
					type: 'partial_refund',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats full_refund events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount_refunded: 10000,
					currency: 'USD',
					datetime: 1586008266,
					deposit: null,
					type: 'full_refund',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_won events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1586017250,
					deposit: {
						arrival_date: 1586103650,
						id: 'dummy_po_5eaada696b2d3',
					},
					fee: 1500,
					type: 'dispute_won',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_lost events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1586055370,
					deposit: {
						arrival_date: 1586141770,
						id: 'dummy_po_5eaada696b2ef',
					},
					fee: 1500,
					type: 'dispute_lost',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_needs_response events with no amount', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: null,
					currency: null,
					datetime: 1585793174,
					deposit: null,
					dispute_id: 'some_id',
					evidence_due_by: 1585879574,
					fee: null,
					reason: 'fraudulent',
					type: 'dispute_needs_response',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_warning_closed events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585793174,
					type: 'dispute_warning_closed',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_charge_refunded events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585793174,
					type: 'dispute_charge_refunded',
				},
			] )
		).toMatchSnapshot();
	} );
} );
