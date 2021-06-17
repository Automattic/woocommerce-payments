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
const callbackMock = () => {};

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
				onConfirmed={ callbackMock }
				onDeclined={ callbackMock }
			/>
		);

		expect( modalContent() ).toMatchSnapshot();
	} );

	test( 'renders correctly when continue button is clicked', () => {
		let confirmed = false;
		const handleConfirmed = () => ( confirmed = true );

		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				onConfirmed={ handleConfirmed }
				onDeclined={ callbackMock }
			/>
		);
		user.click( screen.getByRole( 'button', { name: /Continue/ } ) );

		expect( confirmed ).toBe( true );
		expect( modalContent() ).toMatchSnapshot();
	} );

	test( 'renders correctly when cancel button is clicked', () => {
		let cancelled = false;
		const handleCancelled = () => ( cancelled = true );

		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				onConfirmed={ callbackMock }
				onDeclined={ handleCancelled }
			/>
		);
		user.click( screen.getByRole( 'button', { name: /Cancel/ } ) );

		expect( cancelled ).toBe( true );
		expect( modalContent() ).toMatchSnapshot();
	} );
} );
