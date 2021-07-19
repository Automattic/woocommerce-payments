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
			},
		};
	} );

	it( 'sets `isSetupCompleted = "yes"` if isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: true } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isSetupCompleted
		).toEqual( 'yes' );
	} );

	it( 'does not set `isSetupCompleted = "yes"` if not isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: false } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isSetupCompleted
		).toEqual( 'no' );
	} );
} );
