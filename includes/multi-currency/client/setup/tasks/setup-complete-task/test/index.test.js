/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { WizardTaskContext } from 'multi-currency/interface/functions';
import SetupCompleteTask from '../../setup-complete-task';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

jest.mock( 'multi-currency/interface/data', () => ( {} ) );
jest.mock( 'multi-currency/data', () => ( {
	useDefaultCurrency: jest.fn().mockReturnValue( {
		code: 'USD',
		rate: 1,
		name: 'United States (US) dollar',
		id: 'usd',
		is_default: true,
		flag: '🇺🇸',
		symbol: '$',
	} ),
} ) );

describe( 'SetupComplete', () => {
	beforeEach( () => {
		window.wcpaySettings = {
			multiCurrencySetup: {
				isSetupCompleted: 'no',
			},
		};
	} );

	it( 'sets `isSetupCompleted = "yes"` if isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: true } }>
				<SetupCompleteTask />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.multiCurrencySetup.isSetupCompleted
		).toEqual( 'yes' );
	} );

	it( 'does not set `isSetupCompleted = "yes"` if not isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: false } }>
				<SetupCompleteTask />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.multiCurrencySetup.isSetupCompleted
		).toEqual( 'no' );
	} );
} );
