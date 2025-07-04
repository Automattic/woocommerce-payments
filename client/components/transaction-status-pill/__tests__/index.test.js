/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TransactionStatusPill from '..';

describe( 'Transaction status pill', () => {
	[ 'allow', 'review', 'block', 'unknown' ].forEach( ( status ) => {
		it( `should render the ${ status } pill correctly`, () => {
			const { container } = render(
				<TransactionStatusPill status={ status } />
			);

			expect( container ).toMatchSnapshot();
		} );
	} );
} );
