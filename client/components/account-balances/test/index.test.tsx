/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';
import AccountBalancesHeader from '../header';
import { getGreeting } from '../utils';
import { useCurrentWpUser } from '../hooks';

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

jest.mock( '../utils', () => ( {
	getTimeOfDayString: jest.fn(),
	getGreeting: jest.fn(),
} ) );

jest.mock( '../hooks', () => ( {
	useCurrentWpUser: jest.fn(),
} ) );

const mockGetGreeting = getGreeting as jest.MockedFunction<
	typeof getGreeting
>;
const mockUseCurrentWpUser = useCurrentWpUser as jest.MockedFunction<
	typeof useCurrentWpUser
>;

describe( 'AccountBalances', () => {
	test( 'renders', () => {
		const expectedGreeting = 'Good afternoon, Tester 👋';
		mockGetGreeting.mockReturnValue( expectedGreeting );
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'AccountBalancesHeader', () => {
	test( 'renders the correct greeting in the header', () => {
		const expectedGreeting = 'Good afternoon, Tester 👋';
		mockGetGreeting.mockReturnValue( expectedGreeting );
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <AccountBalancesHeader /> );
		getByText( expectedGreeting );
	} );
} );
