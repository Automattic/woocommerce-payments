/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import SetupComplete from '../setup-complete-task';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

describe( 'SetupComplete', () => {
	beforeEach( () => {
		window.wcpaySettings = {
			additionalMethodsSetup: {
				isSetupCompleted: 'no',
				isUpeEnabled: false,
			},
		};
	} );

	it( 'sets isSetupCompleted and isUpeEnabled if isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: true } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isSetupCompleted
		).toEqual( 'yes' );
		expect(
			window.wcpaySettings.additionalMethodsSetup.isUpeEnabled
		).toBeTruthy();
	} );

	it( 'does not set isSetupCompleted and isUpeEnabled if not isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: false } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isSetupCompleted
		).toEqual( 'no' );
		expect(
			window.wcpaySettings.additionalMethodsSetup.isUpeEnabled
		).toBeFalsy();
	} );
} );
