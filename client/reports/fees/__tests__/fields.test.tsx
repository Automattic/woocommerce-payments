/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getFeesFields } from '../fields';
import type { ReportsFee } from 'wcpay/data/reports/hooks';

jest.mock( 'wcpay/components/details-link', () => ( {
	getDetailsURL: jest.fn(
		( id: string, parentSegment: string ) =>
			`/admin.php?page=wc-admin&path=/payments/${ parentSegment }/details&id=${ id }`
	),
} ) );

jest.mock( 'wcpay/utils', () => ( {
	formatStringValue: ( value: string ) => value,
	getAdminUrl: ( args: Record< string, string | number > ) =>
		`/admin.php?${ new URLSearchParams(
			Object.entries( args ).map( ( [ key, value ] ) => [
				key,
				String( value ),
			] )
		).toString() }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) => `formatted ${ value }`,
} ) );

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: ( amount: number, currency: string ) =>
		`$${ ( amount / 100 ).toFixed( 2 ) } ${ currency.toUpperCase() }`,
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Link: ( {
		children,
		href,
		type,
	}: {
		children: React.ReactNode;
		href: string;
		type?: string;
	} ) => (
		<a href={ href } data-link-type={ type }>
			{ children }
		</a>
	),
} ) );

jest.mock( '../strings', () => ( {
	displayMethod: jest.fn( ( type: string ) =>
		type ? `Method: ${ type }` : ''
	),
	displayType: {
		charge: 'Charge',
		payment: 'Payment',
	},
} ) );

const baseRow: ReportsFee = {
	transaction_id: 'txn_123',
	date: '2026-05-14T10:00:00Z',
	type: 'charge',
	transaction_currency: 'usd',
	amount: 1000,
	deposit_currency: 'usd',
	fees: 30,
	order_id: 4567,
	payment_method: { type: 'card' },
	deposit_date: '2026-05-16',
	deposit_id: 'po_abc',
};

const getTestFeesFields = () =>
	getFeesFields( {
		methodElements: [ { value: 'card', label: 'Card' } ],
		typeElements: [ { value: 'charge', label: 'Charge' } ],
	} );

const renderField = ( fieldId: string, row: ReportsFee ) => {
	const field = getTestFeesFields().find( ( f ) => f.id === fieldId );
	if ( ! field || ! field.render ) {
		throw new Error( `No render for ${ fieldId }` );
	}
	const Render = field.render as React.FC< {
		item: ReportsFee;
		field: typeof field;
	} >;
	return render( <Render item={ row } field={ field } /> );
};

describe( 'getFeesFields render functions', () => {
	it( 'renders the transaction_id column as a link to the transaction details', () => {
		renderField( 'transaction_id', baseRow );
		const link = screen.getByRole( 'link' );
		expect( link ).toHaveAttribute(
			'href',
			expect.stringContaining( 'transaction_id=txn_123' )
		);
		// No tabIndex="-1" — details links should stay keyboard reachable.
		expect( link ).not.toHaveAttribute( 'tabIndex', '-1' );
	} );

	it( 'renders the date column as plain text (not a link)', () => {
		const { container } = renderField( 'date', baseRow );
		expect( container.querySelector( 'a' ) ).toBeNull();
		expect(
			screen.getByText( 'formatted 2026-05-14T10:00:00Z' )
		).toBeInTheDocument();
	} );

	it( 'renders the order_id column as a link to wc-orders when present', () => {
		renderField( 'order_id', baseRow );
		const link = screen.getByRole( 'link' );
		expect( link ).toHaveTextContent( '4567' );
		expect( link ).toHaveAttribute(
			'href',
			'/admin.php?page=wc-orders&action=edit&id=4567'
		);
		expect( link ).toHaveAttribute( 'data-link-type', 'external' );
	} );

	it( 'renders an em-dash when order_id is missing', () => {
		renderField( 'order_id', { ...baseRow, order_id: null } );
		expect( screen.getByText( '–' ) ).toBeInTheDocument();
	} );

	it( 'renders the amount column with explicit currency, no link wrapper', () => {
		const { container } = renderField( 'amount', baseRow );
		expect( container.querySelector( 'a' ) ).toBeNull();
		expect( screen.getByText( /\$10\.00.*USD/ ) ).toBeInTheDocument();
	} );

	it( 'renders the transaction_currency in uppercase', () => {
		renderField( 'transaction_currency', baseRow );
		expect( screen.getByText( 'USD' ) ).toBeInTheDocument();
	} );

	it( 'renders an em-dash when transaction_currency is missing', () => {
		// Cast around the strict type — the REST contract says the field is
		// always a string, but the render path must survive a null value.
		renderField( 'transaction_currency', {
			...baseRow,
			transaction_currency: null as unknown as string,
		} );
		expect( screen.getByText( '–' ) ).toBeInTheDocument();
	} );

	it( 'renders deposit_date with em-dash fallback', () => {
		renderField( 'deposit_date', { ...baseRow, deposit_date: null } );
		expect( screen.getByText( '–' ) ).toBeInTheDocument();
	} );

	it( 'renders a formatted deposit_date when present', () => {
		renderField( 'deposit_date', {
			...baseRow,
			deposit_date: '2026-06-01',
		} );
		expect(
			screen.getByText( 'formatted 2026-06-01' )
		).toBeInTheDocument();
	} );

	it( 'renders the deposit_id column as a link to the payout details when present', () => {
		renderField( 'deposit_id', baseRow );
		const link = screen.getByRole( 'link' );
		expect( link ).toHaveTextContent( 'po_abc' );
		expect( link ).toHaveAttribute(
			'href',
			'/admin.php?page=wc-admin&path=/payments/payouts/details&id=po_abc'
		);
	} );

	it( 'renders an em-dash when deposit_id is missing', () => {
		renderField( 'deposit_id', { ...baseRow, deposit_id: null } );
		expect( screen.getByText( '–' ) ).toBeInTheDocument();
	} );
} );

describe( 'getFeesFields field configuration', () => {
	it( 'does not include an internal date-filter anchor field', () => {
		const fields = getTestFeesFields();
		expect(
			fields.find( ( f ) => f.id === '_wcpay_date_filter_anchor' )
		).toBeUndefined();
	} );

	it( 'marks transaction_id as not hideable', () => {
		const field = getTestFeesFields().find(
			( f ) => f.id === 'transaction_id'
		);
		expect( field?.enableHiding ).toBe( false );
	} );

	it( 'marks date, amount, and fees as sortable', () => {
		const fields = getTestFeesFields();
		[ 'date', 'amount', 'fees' ].forEach( ( id ) => {
			expect( fields.find( ( f ) => f.id === id )?.enableSorting ).toBe(
				true
			);
		} );
	} );

	it( 'configures Date as a native DataViews date filter', () => {
		const field = getTestFeesFields().find( ( f ) => f.id === 'date' );
		expect( field?.label ).toBe( 'Date' );
		expect( field?.header ).toBe( 'Date & time' );
		expect( field?.type ).toBe( 'date' );
		expect( field?.elements ).toBeUndefined();
		expect( field?.filterBy ).toEqual( {
			operators: [ 'on', 'before', 'after', 'between' ],
		} );
	} );

	it( 'configures payment_method and type as native DataViews filters', () => {
		const fields = getTestFeesFields();
		expect( fields.find( ( f ) => f.id === 'payment_method' ) ).toEqual(
			expect.objectContaining( {
				elements: [ { value: 'card', label: 'Card' } ],
				filterBy: { operators: [ 'is' ] },
			} )
		);
		expect( fields.find( ( f ) => f.id === 'type' ) ).toEqual(
			expect.objectContaining( {
				elements: [ { value: 'charge', label: 'Charge' } ],
				filterBy: { operators: [ 'is' ] },
			} )
		);
	} );

	it( 'opts gross amount, fees total, and settlement date out of DataViews filtering', () => {
		const fields = getTestFeesFields();

		[ 'amount', 'fees', 'deposit_date' ].forEach( ( id ) => {
			expect( fields.find( ( f ) => f.id === id )?.filterBy ).toBe(
				false
			);
		} );
	} );
} );
