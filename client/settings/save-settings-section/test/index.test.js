/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SaveSettingsSection from '..';
import { useSettings } from '../../../data';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'SaveSettingsSection', () => {
	it( 'disables the button when loading data', () => {
		useSettings.mockReturnValue( {
			isLoading: true,
		} );

		render( <SaveSettingsSection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'disables the button when saving data', () => {
		useSettings.mockReturnValue( {
			isSaving: true,
		} );

		render( <SaveSettingsSection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'calls `saveSettings` when the button is clicked', () => {
		const saveSettingsMock = jest.fn();
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: saveSettingsMock,
		} );

		render( <SaveSettingsSection /> );

		const saveChangesButton = screen.getByText( 'Save changes' );

		expect( saveSettingsMock ).not.toHaveBeenCalled();
		expect( saveChangesButton ).not.toBeDisabled();

		userEvent.click( saveChangesButton );

		expect( saveSettingsMock ).toHaveBeenCalled();
	} );
} );
