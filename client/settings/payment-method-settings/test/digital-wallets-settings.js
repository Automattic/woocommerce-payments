/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DigitalWalletsSettings from '../digital-wallets-settings';

describe( 'DigitalWalletsSettings', () => {
	test( 'renders settings with defaults', () => {
		render( <DigitalWalletsSettings /> );

		// confirm settings headings
		expect( screen.queryByText( 'Call to action' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Appearance' ) ).toBeInTheDocument();

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
		expect( screen.getByLabelText( 'Dark' ) ).toBeChecked();
	} );
} );
