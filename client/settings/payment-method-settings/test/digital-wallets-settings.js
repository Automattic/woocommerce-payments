/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DigitalWalletsSettings from '../digital-wallets-settings';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
	useDigitalWalletsButtonActionType: jest.fn().mockReturnValue( [ 'buy' ] ),
	useDigitalWalletsButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	useDigitalWalletsButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

describe( 'DigitalWalletsSettings', () => {
	test( 'renders settings with defaults', () => {
		render( <DigitalWalletsSettings /> );

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const [ ctaRadio, sizeRadio, themeRadio ] = screen.getAllByRole(
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
} );
