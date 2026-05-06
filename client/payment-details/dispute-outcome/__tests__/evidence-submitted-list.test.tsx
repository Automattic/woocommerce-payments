/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import EvidenceSubmittedList from '../evidence-submitted-list';
import type { EvidenceFieldStatus } from 'wcpay/disputes/new-evidence/types';
import {
	wonFraudulentPhysical,
	lostProductUnacceptablePhysical,
} from '../__fixtures__/evidence-statuses';

const provided = (
	label = 'Customer communication'
): EvidenceFieldStatus => ( {
	key: 'customer_communication',
	label,
	state: 'provided',
} );

const expectedMissing = ( label = 'Refund policy' ): EvidenceFieldStatus => ( {
	key: 'refund_policy',
	label,
	state: 'expected_missing',
} );

const optionalMissing = ( label = 'Service date' ): EvidenceFieldStatus => ( {
	key: 'service_date',
	label,
	state: 'optional_missing',
} );

describe( 'EvidenceSubmittedList', () => {
	it( 'renders nothing when fields is empty', () => {
		const { container } = render( <EvidenceSubmittedList fields={ [] } /> );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders one list item per field, preserving order', () => {
		const fields = [
			provided( 'A' ),
			expectedMissing( 'B' ),
			optionalMissing( 'C' ),
		];
		render( <EvidenceSubmittedList fields={ fields } /> );

		const items = screen.getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ] ).toHaveTextContent( 'A' );
		expect( items[ 1 ] ).toHaveTextContent( 'B' );
		expect( items[ 2 ] ).toHaveTextContent( 'C' );
	} );

	it( 'announces a "provided" state to assistive tech for provided fields', () => {
		render(
			<EvidenceSubmittedList fields={ [ provided( 'Receipt' ) ] } />
		);
		const item = screen.getByRole( 'listitem' );
		expect( item ).toHaveTextContent( /Receipt/ );
		expect( within( item ).getByText( /provided/i ) ).toBeInTheDocument();
	} );

	it( 'announces a "missing" state to assistive tech for expected_missing fields', () => {
		render(
			<EvidenceSubmittedList
				fields={ [ expectedMissing( 'Refund policy' ) ] }
			/>
		);
		const item = screen.getByRole( 'listitem' );
		expect( item ).toHaveTextContent( /Refund policy/ );
		expect( within( item ).getByText( /missing/i ) ).toBeInTheDocument();
	} );

	it( 'announces a "not provided" state for optional_missing fields', () => {
		render(
			<EvidenceSubmittedList
				fields={ [ optionalMissing( 'Service date' ) ] }
			/>
		);
		const item = screen.getByRole( 'listitem' );
		expect( item ).toHaveTextContent( /Service date/ );
		expect(
			within( item ).getByText( /not provided/i )
		).toBeInTheDocument();
	} );

	it( 'applies a state-specific modifier class so styling can target each state', () => {
		const fields = [
			provided( 'A' ),
			expectedMissing( 'B' ),
			optionalMissing( 'C' ),
		];
		render( <EvidenceSubmittedList fields={ fields } /> );

		const items = screen.getAllByRole( 'listitem' );
		expect( items[ 0 ].className ).toMatch( /provided/ );
		expect( items[ 1 ].className ).toMatch( /expected-missing/ );
		expect( items[ 2 ].className ).toMatch( /optional-missing/ );
	} );

	it( 'renders the "provided" state phrase via a visually-hidden span (not visible inline)', () => {
		render(
			<EvidenceSubmittedList fields={ [ provided( 'Receipt' ) ] } />
		);
		const stateNode = screen.getByText( /provided/i );
		// VisuallyHidden has its own class signature; the visible state suffix
		// uses our BEM modifier class. Make sure the provided phrase is NOT
		// rendered into the visible slot.
		expect( stateNode.className ).not.toMatch(
			/dispute-outcome-evidence-list__state/
		);
	} );

	it( 'renders the "missing" and "not provided" state phrases as visible inline text', () => {
		render(
			<EvidenceSubmittedList
				fields={ [ expectedMissing( 'A' ), optionalMissing( 'B' ) ] }
			/>
		);

		const missingNode = screen.getByText( /^missing$/i );
		const notProvidedNode = screen.getByText( /^not provided$/i );

		expect( missingNode.className ).toMatch(
			/dispute-outcome-evidence-list__state/
		);
		expect( notProvidedNode.className ).toMatch(
			/dispute-outcome-evidence-list__state/
		);
	} );

	it( 'uses the field key as the React key (does not warn about missing keys)', () => {
		const consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );

		render(
			<EvidenceSubmittedList
				fields={ [
					provided( 'A' ),
					{ ...expectedMissing( 'B' ), key: 'refund_policy' },
				] }
			/>
		);

		expect( consoleError ).not.toHaveBeenCalledWith(
			expect.stringMatching( /unique "key" prop/ )
		);
		consoleError.mockRestore();
	} );

	describe( 'fixture variants', () => {
		it( 'renders the Won (fraudulent × physical) fixture with at least one provided field and no expected_missing items', () => {
			render(
				<EvidenceSubmittedList fields={ wonFraudulentPhysical } />
			);

			const items = screen.getAllByRole( 'listitem' );
			expect( items ).toHaveLength( wonFraudulentPhysical.length );

			const expectedMissingItems = items.filter( ( item ) =>
				item.className.includes( 'expected-missing' )
			);
			expect( expectedMissingItems ).toHaveLength( 0 );

			const providedItems = items.filter( ( item ) =>
				item.className.includes( '--provided' )
			);
			expect( providedItems.length ).toBeGreaterThan( 0 );
		} );

		it( 'renders the Lost (product_unacceptable × physical) fixture with at least one expected_missing item', () => {
			render(
				<EvidenceSubmittedList
					fields={ lostProductUnacceptablePhysical }
				/>
			);

			const items = screen.getAllByRole( 'listitem' );
			expect( items ).toHaveLength(
				lostProductUnacceptablePhysical.length
			);

			const expectedMissingItems = items.filter( ( item ) =>
				item.className.includes( 'expected-missing' )
			);
			expect( expectedMissingItems.length ).toBeGreaterThan( 0 );
		} );
	} );
} );
