/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsManager from '..';

describe( 'SettingsManager', () => {
	it( 'renders the PaymentMethods section', () => {
		render( <SettingsManager /> );

		expect(
			screen.queryByText( 'Payments accepted on checkout' )
		).toBeInTheDocument();
	} );
} );
