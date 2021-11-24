/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import os from 'os';

/**
 * Internal dependencies
 */
import DisputesList from '../';
import { useDisputes } from 'wcpay/data';
import { formatStringValue } from 'utils';

jest.mock( 'wcpay/data', () => ( {
	useDisputes: jest.fn(),
} ) );

jest.mock( '@woocommerce/csv-export', () => {
	const actualModule = jest.requireActual( '@woocommerce/csv-export' );

	return {
		...actualModule,
		downloadCSVFile: jest.fn(),
	};
} );

const mockDisputes = [
	{
		id: 'dp_asdfghjkl',
		amount: 1000,
		currency: 'usd',
		created: 1572590800,
		evidence_details: {
			due_by: 1573199200,
		},
		reason: 'fraudulent',
		status: 'needs_response',
		charge: {
			id: 'ch_mock',
			payment_method_details: {
				card: {
					brand: 'visa',
				},
			},
			billing_details: {
				name: 'Mock customer',
				email: 'mock@customer.net',
				address: {
					country: 'US',
				},
			},
		},
		order: {
			number: '1',
			url: 'http://test.local/order/1',
		},
	},
	{
		id: 'dp_zxcvbnm',
		amount: 1050,
		currency: 'usd',
		created: 1572480800,
		evidence_details: {
			due_by: 1573099200,
		},
		reason: 'general',
		status: 'under_review',
		// dispute without order or charge information
	},
];

describe( 'Disputes list', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		useDisputes.mockReturnValue( {
			isLoading: false,
			disputes: mockDisputes,
		} );

		const { container: list } = render( <DisputesList /> );
		expect( list ).toMatchSnapshot();
	} );

	describe( 'Download button', () => {
		test( 'renders when there are one or more disputes', () => {
			useDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no disputes', () => {
			useDisputes.mockReturnValue( {
				disputes: [],
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			useDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );
		} );

		afterEach( () => {
			jest.resetAllMocks();
		} );

		afterAll( () => {
			jest.restoreAllMocks();
		} );

		test( 'should render expected columns in CSV when the download button is clicked ', () => {
			const { getByRole } = render( <DisputesList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const expected = [
				'"Dispute Id"',
				'Amount',
				'Status',
				'Reason',
				'Source',
				'"Order #"',
				'Customer',
				'Email',
				'Country',
				'"Disputed on"',
				'"Respond by"',
			];

			const csvContent = downloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvHeaderRow = csvContent.split( os.EOL )[ 0 ].split( ',' );
			expect( csvHeaderRow ).toEqual( expected );
		} );

		test( 'file should have the correct content', () => {
			const { getByRole } = render( <DisputesList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const csvContent = downloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvRows = csvContent.split( os.EOL ).slice( 1 );

			expect( csvRows.length ).toEqual( mockDisputes.length );

			const csvFirstDispute = csvRows[ 0 ].split( ',' );
			const {
				amount,
				status,
				reason,
				order,
				charge,
				created,
				evidence_details: evidenceDetails,
			} = mockDisputes[ 0 ];

			expect( csvFirstDispute[ 1 ] ).toBe( ( amount / 100 ).toString() );

			expect( csvFirstDispute[ 2 ] ).toBe(
				`"${ formatStringValue( status ) }"`
			);

			expect( csvFirstDispute[ 3 ] ).toBe( formatStringValue( reason ) );

			expect( csvFirstDispute[ 5 ] ).toBe(
				formatStringValue( order.number )
			);

			expect( csvFirstDispute[ 6 ] ).toBe(
				`"${ charge.billing_details.name }"`
			);

			expect( csvFirstDispute[ 9 ] ).toBe(
				dateI18n( 'Y-m-d', moment( created * 1000 ).toISOString() )
			);

			expect( csvFirstDispute[ 10 ] ).toBe(
				`"${ dateI18n(
					'Y-m-d / g:iA',
					moment( evidenceDetails.due_by * 1000 ).toISOString()
				) }"`
			);
		} );
	} );
} );
