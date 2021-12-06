/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ReceiptSettings from '..';

describe( 'ReceiptSettings', () => {
	test( 'Readers merchant settings page renders', () => {
		render( <ReceiptSettings /> );

		expect(
			screen.queryByText( 'Card reader receipts' )
		).toBeInTheDocument();
	} );

	test( 'Readers merchant settings page snapshot test', () => {
		const { container } = render( <ReceiptSettings /> );
		expect( container ).toMatchSnapshot();
	} );
} );
