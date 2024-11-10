/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MultiCurrencySetup from '../multi-currency-setup';
import StoreSettingsTask from '../store-settings-task';
import SetupCompleteTask from '../setup-complete-task';
import AddCurrenciesTask from '../add-currencies-task';

jest.mock( '../store-settings-task', () => jest.fn() );
jest.mock( '../setup-complete-task', () => jest.fn() );
jest.mock( '../add-currencies-task', () => jest.fn() );

const createContainer = () => {
	const { container } = render( <MultiCurrencySetup /> );
	return container;
};

describe( 'Multi-Currency enabled currencies list', () => {
	beforeEach( () => {
		AddCurrenciesTask.mockReturnValue( <p>Currency selector</p> );
		StoreSettingsTask.mockReturnValue( <p>Store settings form</p> );
		SetupCompleteTask.mockReturnValue( <p>Setup complete screen</p> );
	} );
	test( 'wizard renders correctly', () => {
		const container = createContainer();
		expect( container ).toMatchSnapshot(
			'snapshot-multi-currency-setup-wizard'
		);
	} );
} );
