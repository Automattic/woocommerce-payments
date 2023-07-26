/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositStatusPill from '..';

describe( 'Deposits status pill renders', () => {
	test( 'Renders In Transit status pill.', () => {
		const { getByText } = render(
			<DepositStatusPill status="estimated" />
		);
		expect( getByText( 'Estimated' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status pill.', () => {
		const { getByText } = render( <DepositStatusPill status="pending" /> );
		expect( getByText( 'Pending' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status pill.', () => {
		const { getByText } = render( <DepositStatusPill status="paid" /> );
		expect( getByText( 'Paid' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status pill.', () => {
		const { getByText } = render(
			<DepositStatusPill status="in_transit" />
		);
		expect( getByText( 'In transit' ) ).toBeTruthy();
	} );
} );
