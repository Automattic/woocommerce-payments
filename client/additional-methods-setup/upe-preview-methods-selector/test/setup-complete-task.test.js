/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import SetupComplete from '../setup-complete-task';
import WizardContext from '../../wizard/wrapper/context';
import { useEnabledPaymentMethodIds } from '../../../data';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );
jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'SetupComplete', () => {
	beforeEach( () => {
		window.wcpaySettings = {
			additionalMethodsSetup: {
				isUpeEnabled: false,
			},
		};

		useEnabledPaymentMethodIds.mockReturnValue( [
			[
				'card',
				'bancontact',
				'eps',
				'giropay',
				'sofort',
				'ideal',
				'p24',
				'sepa_debit',
			],
			() => null,
		] );
	} );

	it( 'sets isUpeEnabled if isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: true } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isUpeEnabled
		).toBeTruthy();
	} );

	it( 'does not set isUpeEnabled if not isActive', () => {
		render(
			<WizardTaskContext.Provider value={ { isActive: false } }>
				<SetupComplete />
			</WizardTaskContext.Provider>
		);

		expect(
			window.wcpaySettings.additionalMethodsSetup.isUpeEnabled
		).toBeFalsy();
	} );

	it( 'renders setup complete messaging when context value is undefined', () => {
		render(
			<WizardContext.Provider value={ { completedTasks: {} } }>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect(
			screen.queryByText( /Setup complete/ )
		).not.toBeInTheDocument();
	} );

	it( 'renders setup complete messaging when context value is `true`', () => {
		render(
			<WizardContext.Provider
				value={ { completedTasks: { 'add-payment-methods': true } } }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete!'
		);
	} );

	it( 'renders setup complete messaging when context value says that methods have not changed', () => {
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [
								'card',
								'bancontact',
								'eps',
								'giropay',
								'ideal',
								'p24',
								'sofort',
								'sepa_debit',
							],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete!'
		);
	} );

	it( 'renders setup complete messaging when context value says that one payment method has been removed', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sofort' ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [
								'card',
								'bancontact',
								'eps',
								'giropay',
								'ideal',
								'p24',
								'sofort',
								'sepa_debit',
							],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete!'
		);
	} );

	it( 'renders setup complete messaging when context value says that one payment method has been added', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete! One new payment method is now live on your store!'
		);
	} );

	it( 'renders setup complete messaging when context value says that more than one payment method has been added', () => {
		const additionalMethods = [
			'bancontact',
			'eps',
			'giropay',
			'ideal',
			'p24',
			'sofort',
			'sepa_debit',
		];
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', ...additionalMethods ],
			() => null,
		] );
		render(
			<WizardContext.Provider
				value={ {
					completedTasks: {
						'add-payment-methods': {
							initialMethods: [ 'card' ],
						},
					},
				} }
			>
				<WizardTaskContext.Provider value={ { isActive: true } }>
					<SetupComplete />
				</WizardTaskContext.Provider>
			</WizardContext.Provider>
		);

		expect( screen.getByText( /Setup complete/ ) ).toHaveTextContent(
			'Setup complete! ' +
				additionalMethods.length +
				' new payment methods are now live on your store!'
		);
	} );
} );
