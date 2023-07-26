/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositStatusChip from '..';

describe( 'Deposits status chip renders', () => {
	test( 'Renders default status chip "unknown" when unknown/invalid status is given.', () => {
		const { getByText } = render( <DepositStatusChip status="hello" /> );

		expect( getByText( 'Unknown' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status chip.', () => {
		const { getByText } = render(
			<DepositStatusChip status="estimated" />
		);
		expect( getByText( 'Estimated' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status chip.', () => {
		const { getByText } = render( <DepositStatusChip status="pending" /> );
		expect( getByText( 'Pending' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status chip.', () => {
		const { getByText } = render( <DepositStatusChip status="paid" /> );
		expect( getByText( 'Paid' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status chip.', () => {
		const { getByText } = render(
			<DepositStatusChip status="in_transit" />
		);
		expect( getByText( 'In transit' ) ).toBeTruthy();
	} );
} );
