/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TransactionStatusPill from '.';
import { TransactionStatus } from './mappings';

describe( 'Transaction status pill', () => {
	[ 'allow', 'review', 'block', 'unknown' ].forEach( ( status: string ) => {
		it( `should render the ${ status } pill correctly`, () => {
			const { container } = render(
				<TransactionStatusPill status={ status as TransactionStatus } />
			);

			expect( container ).toMatchSnapshot();
		} );
	} );
} );
