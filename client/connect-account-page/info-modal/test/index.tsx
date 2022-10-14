/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import OnboardingMoreInfoModal from '..';

const setModalOpenStub = jest.fn();
// Utility function for accessing the modal content in assertions
const modalContent = () => {
	const selector = '.woocommerce-payments__onboarding_more_info-modal';
	return document.body.querySelector( selector );
};

describe( 'Onboarding: more info modal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when opened', () => {
		render(
			<OnboardingMoreInfoModal handleModalClose={ setModalOpenStub } />
		);

		expect( modalContent() ).toMatchSnapshot();
	} );
} );
