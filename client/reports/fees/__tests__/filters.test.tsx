/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { FeesFilters } from '../filters';
import {
	getFeesAdvancedFilters,
	getFeesFilterOptionsFromSummary,
	getFeesFilters,
} from '../filters/config';
import { recordEvent } from 'tracks';

const reportFiltersMock = jest.fn( ( props: Record< string, unknown > ) => {
	void props;
	return null;
} );

jest.mock( '@woocommerce/components', () => ( {
	ReportFilters: ( props: Record< string, unknown > ) => {
		reportFiltersMock( props );
		return null;
	},
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'Fees report filters', () => {
	beforeEach( () => {
		reportFiltersMock.mockClear();
		jest.mocked( recordEvent ).mockClear();
		( window as any ).wooPaymentsPaymentMethodsConfig = {
			card: {
				title: 'Card',
			},
		};
	} );

	test( 'builds the Date, Method, and Type advanced filter config', () => {
		const advancedFilters = getFeesAdvancedFilters(
			[ { label: 'Card', value: 'card' } ],
			[ { label: 'Charge', value: 'charge' } ]
		);

		expect( Object.keys( advancedFilters.filters ) ).toStrictEqual( [
			'date',
			'payment_method',
			'type',
		] );
		expect(
			advancedFilters.filters.date.rules.map(
				( { value }: { value: string } ) => value
			)
		).toStrictEqual( [ 'before', 'after', 'between' ] );
		expect( advancedFilters.filters.date.input.component ).toBe( 'Date' );
		expect( advancedFilters.filters.payment_method.rules ).toMatchObject( [
			{ value: 'type', label: 'Is' },
		] );
		expect( advancedFilters.filters.payment_method.input.options ).toEqual(
			[ { label: 'Card', value: 'card' } ]
		);
		expect( advancedFilters.filters.type.rules ).toMatchObject( [
			{ value: '', label: 'Is' },
		] );
		expect( advancedFilters.filters.type.input.options ).toEqual( [
			{ label: 'Charge', value: 'charge' },
		] );
	} );

	test( 'maps advanced filters to Fees report query params', () => {
		const advancedFilters = getFeesAdvancedFilters(
			[ { label: 'Card', value: 'card' } ],
			[ { label: 'Charge', value: 'charge' } ]
		);
		const getUrlKey = ( key: string, rule?: string ) =>
			rule && rule.length ? `${ key }_${ rule }` : key;
		const activeFilters = [
			{
				key: 'date',
				rule: 'between',
				value: [ '2026-04-01', '2026-04-30' ],
			},
			{
				key: 'payment_method',
				rule: 'type',
				value: 'card',
			},
			{
				key: 'type',
				rule: '',
				value: 'charge',
			},
		];

		expect(
			activeFilters.reduce< Record< string, unknown > >(
				( query, filter ) => ( {
					...query,
					[ getUrlKey( filter.key, filter.rule ) ]: filter.value,
				} ),
				{}
			)
		).toStrictEqual( {
			date_between: [ '2026-04-01', '2026-04-30' ],
			payment_method_type: 'card',
			type: 'charge',
		} );
		expect( Object.keys( advancedFilters.filters ) ).toContain(
			'payment_method'
		);
	} );

	test( 'derives method and type options from the report summary', () => {
		expect(
			getFeesFilterOptionsFromSummary( {
				sources: [ 'card' ],
				types: [ 'charge' ],
			} )
		).toStrictEqual( {
			methodOptions: [ { label: 'Card', value: 'card' } ],
			typeOptions: [ { label: 'Charge', value: 'charge' } ],
		} );
	} );

	test( 'renders ReportFilters with the Reports path and records advanced filter events', () => {
		render(
			<FeesFilters
				feesSummary={ {
					sources: [ 'card' ],
					types: [ 'charge' ],
				} }
			/>
		);

		expect( reportFiltersMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				advancedFilters: expect.objectContaining( {
					filters: expect.any( Object ),
				} ),
				filters: getFeesFilters(),
				path: '/payments/reports',
				showDatePicker: false,
			} )
		);

		const props = reportFiltersMock.mock.calls[ 0 ][ 0 ] as {
			onAdvancedFilterAction: ( event: string ) => void;
		};
		props.onAdvancedFilterAction( 'filter' );

		expect( recordEvent ).toHaveBeenCalledWith( 'page_view', {
			path: 'payments_reports',
			filter: 'advanced',
		} );
	} );

	test( 'records a date range event when date filters are applied', () => {
		render(
			<FeesFilters
				feesSummary={ {
					sources: [ 'card' ],
					types: [ 'charge' ],
				} }
			/>
		);

		const props = reportFiltersMock.mock.calls[ 0 ][ 0 ] as {
			onAdvancedFilterAction: (
				event: string,
				query: Record< string, unknown >
			) => void;
		};
		props.onAdvancedFilterAction( 'filter', {
			date_between: [ '2026-04-01', '2026-04-30' ],
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_date_range_changed',
			{
				report: 'fees',
			}
		);
	} );
} );
