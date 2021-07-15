/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AdditionalMethodsSetupContext from '../../context';
import WizardTaskContext from '../../wizard/task/context';
import SetupComplete from '../setup-complete-task';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

describe( 'SetupComplete', () => {
	it( 'calls setSetupCompleted() and setUpeEnabled() if isActive', () => {
		const setSetupCompleted = jest.fn();
		const setUpeEnabled = jest.fn();

		render(
			<AdditionalMethodsSetupContext.Provider
				value={ { setSetupCompleted, setUpeEnabled } }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</AdditionalMethodsSetupContext.Provider>
		);

		expect( setSetupCompleted ).toHaveBeenCalledTimes( 1 );
		expect( setUpeEnabled ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not call setSetupCompleted() and setUpeEnabled() if not isActive', () => {
		const setSetupCompleted = jest.fn();
		const setUpeEnabled = jest.fn();

		render(
			<AdditionalMethodsSetupContext.Provider
				value={ { setSetupCompleted, setUpeEnabled } }
			>
				<WizardTaskContext.Provider value={ { isActive: false } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</AdditionalMethodsSetupContext.Provider>
		);

		expect( setSetupCompleted ).not.toHaveBeenCalled();
		expect( setUpeEnabled ).not.toHaveBeenCalled();
	} );
} );
