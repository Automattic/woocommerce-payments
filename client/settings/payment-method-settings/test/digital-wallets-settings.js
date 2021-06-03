/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DigitalWalletsSettings from '../digital-wallets-settings';
import {
	useDigitalWalletsButtonActionType,
	useDigitalWalletsButtonSize,
	useDigitalWalletsButtonTheme,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	useDigitalWalletsButtonActionType: jest.fn().mockReturnValue( [ 'buy' ] ),
	useDigitalWalletsButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	useDigitalWalletsButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

describe( 'DigitalWalletsSettings', () => {
	it( 'renders settings with defaults', () => {
		render( <DigitalWalletsSettings /> );

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const [ ctaRadio, sizeRadio, themeRadio ] = screen.queryAllByRole(
			'radio'
		);

		expect( ctaRadio ).toBeInTheDocument();
		expect( sizeRadio ).toBeInTheDocument();
		expect( themeRadio ).toBeInTheDocument();

		// confirm default values
		expect( screen.getByLabelText( 'Buy' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Default (40 px)' ) ).toBeChecked();
		expect( screen.getByLabelText( /Dark/ ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the settings are being interacted with', () => {
		const setButtonActionTypeMock = jest.fn();
		const setButtonSizeMock = jest.fn();
		const setButtonThemeMock = jest.fn();
		useDigitalWalletsButtonActionType.mockReturnValue( [
			'buy',
			setButtonActionTypeMock,
		] );
		useDigitalWalletsButtonSize.mockReturnValue( [
			'default',
			setButtonSizeMock,
		] );
		useDigitalWalletsButtonTheme.mockReturnValue( [
			'dark',
			setButtonThemeMock,
		] );

		render( <DigitalWalletsSettings /> );

		expect( setButtonActionTypeMock ).not.toHaveBeenCalled();
		expect( setButtonSizeMock ).not.toHaveBeenCalled();
		expect( setButtonThemeMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Light/ ) );
		expect( setButtonThemeMock ).toHaveBeenCalledWith( 'light' );

		userEvent.click( screen.getByLabelText( 'Book' ) );
		expect( setButtonActionTypeMock ).toHaveBeenCalledWith( 'book' );

		userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );
		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
	} );
} );
