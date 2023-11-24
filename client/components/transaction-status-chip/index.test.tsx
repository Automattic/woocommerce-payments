/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TransactionStatusChip from '.';
import { TransactionStatus } from './mappings';

describe( 'Transaction status chip', () => {
	[ 'allow', 'review', 'block', 'unknown' ].forEach( ( status: string ) => {
		it( `should render the ${ status } chip correctly`, () => {
			const { container } = render(
				<TransactionStatusChip status={ status as TransactionStatus } />
			);

			expect( container ).toMatchSnapshot();
		} );
	} );
} );
