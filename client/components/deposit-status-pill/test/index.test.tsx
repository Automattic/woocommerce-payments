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
	test( 'Renders default status pill "estimated" when unknown status is given.', () => {
		const { getByText } = render( <DepositStatusPill status="hello" /> );

		expect( getByText( 'Estimated' ) ).toBeTruthy();
	} );

	test( 'Renders In Transit status pill.', () => {
		const { getByText } = render(
			<DepositStatusPill status="in_transit" />
		);
		expect( getByText( 'In transit' ) ).toBeTruthy();
	} );
} );
