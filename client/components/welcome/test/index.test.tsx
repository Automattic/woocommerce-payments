/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Welcome from '..';
import { useCurrentWpUser } from '../hooks';

jest.mock( '../hooks', () => ( {
	useCurrentWpUser: jest.fn(),
} ) );

const mockUseCurrentWpUser = useCurrentWpUser as jest.MockedFunction<
	typeof useCurrentWpUser
>;

describe( 'Welcome', () => {
	test( 'renders the correct greeting when the user first name exists', () => {
		const mockUser = {
			id: 123,
			first_name: 'Tester',
			username: 'admin',
			name: 'admin',
			nickname: 'Tester-nickname',
			last_name: 'Tester-lastname',
			email: 'tester@test.com',
			locale: 'en',
		};
		const expectedGreeting = /Good (morning|afternoon|evening), Tester 👋/;
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <Welcome /> );
		getByText( expectedGreeting );
	} );

	test( 'renders the correct greeting when the user first name is empty', () => {
		const mockUser = {
			id: 123,
			first_name: '',
			username: 'admin',
			name: 'admin',
			nickname: 'Tester-nickname',
			last_name: 'Tester-lastname',
			email: 'tester@test.com',
			locale: 'en',
		};
		const expectedGreeting = /Good (morning|afternoon|evening) 👋/;
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <Welcome /> );
		getByText( expectedGreeting );
	} );
} );
