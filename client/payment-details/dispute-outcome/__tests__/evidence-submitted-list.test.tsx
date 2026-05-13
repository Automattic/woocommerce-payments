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
	// Guarantees console.error spies are restored even if an assertion
	// throws before an explicit mockRestore() runs, preventing mock leak
	// across tests.
	afterEach( () => {
		jest.restoreAllMocks();
	} );

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

	it( 'renders a "Not provided" suffix for both missing states (icon and color carry the urgency)', () => {
		render(
			<EvidenceSubmittedList
				fields={ [
					expectedMissing( 'Refund policy' ),
					optionalMissing( 'Service date' ),
				] }
			/>
		);

		const items = screen.getAllByRole( 'listitem' );
		expect( items[ 0 ] ).toHaveTextContent( /Refund policy/ );
		expect(
			within( items[ 0 ] ).getByText( /not provided/i )
		).toBeInTheDocument();

		expect( items[ 1 ] ).toHaveTextContent( /Service date/ );
		expect(
			within( items[ 1 ] ).getByText( /not provided/i )
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

	it( 'renders the missing-state suffix as visible inline text on every missing row', () => {
		render(
			<EvidenceSubmittedList
				fields={ [ expectedMissing( 'A' ), optionalMissing( 'B' ) ] }
			/>
		);

		const stateNodes = screen.getAllByText( /^not provided$/i );
		expect( stateNodes ).toHaveLength( 2 );
		stateNodes.forEach( ( node ) => {
			expect( node.className ).toMatch(
				/dispute-outcome-evidence-list__state/
			);
		} );
	} );

	it( 'wraps the label and state suffix inside a __text container so long labels can wrap without overflowing', () => {
		render(
			<EvidenceSubmittedList
				fields={ [ expectedMissing( 'A long label' ) ] }
			/>
		);
		const item = screen.getByRole( 'listitem' );
		const labelNode = within( item ).getByText( 'A long label' );
		const stateNode = within( item ).getByText( /not provided/i );

		// Both must share the same __text parent so they flow as one
		// wrapping text block (rather than sitting in separate flex columns).
		expect( labelNode.parentElement?.className ).toMatch(
			/dispute-outcome-evidence-list__text/
		);
		expect( stateNode.parentElement?.className ).toMatch(
			/dispute-outcome-evidence-list__text/
		);
		expect( labelNode.parentElement ).toBe( stateNode.parentElement );
	} );

	it( 'does not emit a React missing-key warning when fields have valid keys', () => {
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

		// React 18 calls console.error with format-string + interpolation args,
		// so single-arg matchers like toHaveBeenCalledWith( stringMatching ) miss
		// the warning. Inspect every call's args and look for the substring.
		const sawUniqueKeyWarning = consoleError.mock.calls.some( ( args ) =>
			args.some(
				( arg ) =>
					typeof arg === 'string' && /unique "key" prop/.test( arg )
			)
		);
		expect( sawUniqueKeyWarning ).toBe( false );
	} );

	it( 'uses field.key as the React key (duplicates trigger React warning)', () => {
		// Render two fields whose `key` properties collide. If the component
		// uses `field.key` as the React key (the contract), React detects the
		// duplicate and warns. Any other keying strategy (e.g. array index)
		// would mask the collision and the warning would not fire.
		const consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );

		render(
			<EvidenceSubmittedList
				fields={ [
					{ key: 'shared_key', label: 'A', state: 'provided' },
					{ key: 'shared_key', label: 'B', state: 'provided' },
				] }
			/>
		);

		const sawDuplicateKeyWarning = consoleError.mock.calls.some( ( args ) =>
			args.some(
				( arg ) =>
					typeof arg === 'string' &&
					/two children with the same key/.test( arg )
			)
		);
		expect( sawDuplicateKeyWarning ).toBe( true );
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
