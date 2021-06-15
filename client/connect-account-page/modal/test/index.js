/**
 * External dependencies
 */
import { render } from '@testing-library/react';

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
	return document.body.getElementsByClassName(
		'woocommerce-payments__onboarding_location_check-modal'
	);
};

describe( 'Onboarding: location check dialog', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when opened', () => {
		render(
			<OnboardingLocationCheckModal
				countries={ countriesMock }
				whenConfirmed={ callbackMock }
				whenDeclined={ callbackMock }
			/>
		);
		expect( modalContent() ).toMatchSnapshot();
	} );
} );
