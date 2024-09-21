/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsLayout from '../../../settings/settings-layout';
import EnabledCurrenciesList from '../enabled-currencies-list';
import StoreSettings from '../store-settings';

jest.mock( '../enabled-currencies-list', () => jest.fn() );
jest.mock( '../store-settings', () => jest.fn() );

const getContainer = () => {
	return render(
		<SettingsLayout displayBanner={ false }>
			<EnabledCurrenciesList />
			<StoreSettings />
		</SettingsLayout>
	);
};

describe( 'Multi-Currency Settings', () => {
	beforeEach( () => {
		EnabledCurrenciesList.mockReturnValue( <p>Enabled currencies list</p> );
		StoreSettings.mockReturnValue( <p>Store settings form</p> );
	} );
	test( 'page renders correctly', () => {
		const container = getContainer();
		expect( container ).toMatchSnapshot(
			'snapshot-multi-currency-setup-wizard'
		);
	} );
} );
