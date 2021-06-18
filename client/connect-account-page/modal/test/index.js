/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import OnboardingLocationCheckModal from '../index';

// Mocks, needed for invoking the modal rendering
const countriesMock = [
	{ title: 'United Kingdom (UK)' },
	{ title: 'United States (US)' },
];
const handleConfirmedStub = jest.fn();
const handleDeclinedStub = jest.fn();

// Utility function for accessing the modal content in assertions
const modalContent = () => {
	const selector = '.woocommerce-payments__onboarding_location_check-modal';
	return document.body.querySelector( selector );
};

describe( 'Onboarding: location check dialog', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when opened', () => {
		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				onConfirmed={ handleConfirmedStub }
				onDeclined={ handleDeclinedStub }
			/>
		);

		expect( modalContent() ).toMatchSnapshot();
	} );

	test( 'renders correctly when continue button is clicked', () => {
		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				onConfirmed={ handleConfirmedStub }
				onDeclined={ handleDeclinedStub }
			/>
		);
		user.click( screen.getByRole( 'button', { name: /Continue/ } ) );

		expect( handleConfirmedStub ).toHaveBeenCalled();
		expect( modalContent() ).toMatchSnapshot();
	} );

	test( 'renders correctly when cancel button is clicked', () => {
		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				onConfirmed={ handleConfirmedStub }
				onDeclined={ handleDeclinedStub }
			/>
		);
		user.click( screen.getByRole( 'button', { name: /Cancel/ } ) );

		expect( handleDeclinedStub ).toHaveBeenCalled();
		expect( modalContent() ).toMatchSnapshot();
	} );
} );
