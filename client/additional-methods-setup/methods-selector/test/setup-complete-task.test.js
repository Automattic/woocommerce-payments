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
	it( 'calls setSetupCompleted() if isActive', () => {
		const setSetupCompleted = jest.fn();

		render(
			<AdditionalMethodsSetupContext.Provider
				value={ { setSetupCompleted } }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</AdditionalMethodsSetupContext.Provider>
		);

		expect( setSetupCompleted ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not call setSetupCompleted() if not isActive', () => {
		const setSetupCompleted = jest.fn();

		render(
			<AdditionalMethodsSetupContext.Provider
				value={ { setSetupCompleted } }
			>
				<WizardTaskContext.Provider value={ { isActive: false } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</AdditionalMethodsSetupContext.Provider>
		);

		expect( setSetupCompleted ).not.toHaveBeenCalled();
	} );
} );
