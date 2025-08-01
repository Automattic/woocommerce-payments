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
		global.wcpaySettings = {};

		render( <SettingsManager /> );

		expect(
			screen.queryByText( 'Payments accepted on checkout' )
		).toBeInTheDocument();
	} );

	it( 'renders the Fraud Protection settings section', () => {
		global.wcpaySettings = {};
		render( <SettingsManager /> );

		expect( screen.queryByText( 'Fraud protection' ) ).toBeInTheDocument();
	} );
} );
