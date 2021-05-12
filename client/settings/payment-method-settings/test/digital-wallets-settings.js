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
	test( 'renders settings', () => {
		render( <DigitalWalletsSettings /> );

		expect(
			screen.queryByText( 'Digital Wallets placeholder.' )
		).toBeInTheDocument();
	} );
} );
