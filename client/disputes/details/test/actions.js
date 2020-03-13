/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Actions from '../actions';

describe( 'Dispute details actions', () => {
	test( 'renders correctly for dispute needing response, confirmation requested on submit', () => {
		window.confirm = jest.fn();
		const doAccept = jest.fn();

		const { container, getByText } = render(
			<Actions
				id="dp_mock"
				needsResponse={ true }
				onAccept={ doAccept }
			/>
		);
		expect( container ).toMatchSnapshot();

		fireEvent.click( getByText( 'Accept Dispute' ) );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( doAccept ).toHaveBeenCalledTimes( 0 );
	} );

	test( 'onAccept called after confirmation only', () => {
		const doAccept = jest.fn();

		const { getByText } = render(
			<Actions
				id="dp_mock"
				needsResponse={ true }
				onAccept={ doAccept }
			/>
		);

		window.confirm = jest.fn()
			.mockReturnValueOnce( false )
			.mockReturnValueOnce( true );

		fireEvent.click( getByText( 'Accept Dispute' ) );
		expect( doAccept ).toHaveBeenCalledTimes( 0 );
		fireEvent.click( getByText( 'Accept Dispute' ) );
		expect( doAccept ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'renders correctly for closed dispute', () => {
		const { container } = render(
			<Actions id="dp_mock" needsResponse={ false } isSubmitted={ false } />
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly for dispute with evidence submitted', () => {
		const { container } = render(
			<Actions id="dp_mock" needsResponse={ false } isSubmitted={ true } />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
