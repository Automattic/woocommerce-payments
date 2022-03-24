/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import OnboardingPage from '..';
import AddBusinessInfoTask from '../tasks/add-business-info-task';
import SetupCompleteTask from '../tasks/setup-complete-task';

jest.mock( '../tasks/add-business-info-task', () => jest.fn() );
jest.mock( '../tasks/setup-complete-task', () => jest.fn() );

describe( 'OnboardingPage', () => {
	beforeEach( () => {
		AddBusinessInfoTask.mockReturnValue( <p>Add Business Info</p> );
		SetupCompleteTask.mockReturnValue( <p>Setup Complete</p> );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'renders page', () => {
		const { container: list } = render( <OnboardingPage /> );
		expect( list ).toMatchSnapshot();
	} );
} );
