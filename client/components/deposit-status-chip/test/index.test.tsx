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
	test( 'Renders "Pending" status chip.', () => {
		const { getByText } = render(
			<DepositStatusChip
				deposit={ { type: 'deposit', status: 'pending' } }
			/>
		);
		expect( getByText( 'Pending' ) ).toBeTruthy();
	} );

	test( 'Renders "Paid" status chip.', () => {
		const { getByText } = render(
			<DepositStatusChip
				deposit={ { type: 'deposit', status: 'paid' } }
			/>
		);
		expect( getByText( 'Completed (paid)' ) ).toBeTruthy();
	} );

	test( 'Renders "In transit" status chip.', () => {
		const { getByText } = render(
			<DepositStatusChip
				deposit={ { type: 'deposit', status: 'in_transit' } }
			/>
		);
		expect( getByText( 'In transit' ) ).toBeTruthy();
	} );
} );
