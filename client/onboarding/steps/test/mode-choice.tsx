/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ModeChoice from '../mode-choice';
import strings from '../../strings';

let nextStep = jest.fn();

jest.mock( 'components/stepper', () => ( {
	useStepperContext: jest.fn( () => ( {
		nextStep,
	} ) ),
} ) );

declare const global: {
	wcpaySettings: {
		connectUrl: string;
		devMode: boolean;
	};
};

describe( 'ModeChoice', () => {
	it( 'displays test and live radio cards, notice for sandbox mode', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
			devMode: true,
		};
		render( <ModeChoice /> );

		expect(
			screen.getByText( strings.steps.mode.label )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Sandbox mode is enabled, only test accounts will be created. If you want to process live transactions, please disable it.'
			)
		).toBeInTheDocument();
	} );

	it( 'calls nextStep by clicking continue when `live` is selected', () => {
		nextStep = jest.fn();
		render( <ModeChoice /> );

		user.click( screen.getByTestId( 'live-mode-button' ) );

		expect( nextStep ).toHaveBeenCalled();
	} );

	it( 'redirects to `connectUrl` with `test_mode` enabled by clicking continue button when `test` is selected', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
			devMode: false,
		};
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render( <ModeChoice /> );

		user.click(
			screen.getByRole( 'button', { name: /Continue in sandbox mode/i } )
		);

		expect( window.location.href ).toBe(
			`https://wcpay.test/connect?test_mode=true`
		);
	} );
} );
