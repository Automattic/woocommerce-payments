/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PersonalDetails from '../personal-details';
import { OnboardingContextProvider } from '../../context';
import strings from '../../strings';

declare const global: {
	wcpaySettings: {
		connect: { country: string };
	};
};

describe( 'PersonalDetails', () => {
	it( 'renders and updates fields data when they are changed', () => {
		global.wcpaySettings = {
			connect: { country: 'US' },
		};

		render(
			<OnboardingContextProvider>
				<PersonalDetails />
			</OnboardingContextProvider>
		);
		const firstNameField = screen.getByLabelText(
			strings.fields[ 'individual.first_name' ]
		);
		const lastNameField = screen.getByLabelText(
			strings.fields[ 'individual.last_name' ]
		);
		const emailField = screen.getByLabelText( strings.fields.email );
		const phoneField = screen.getByLabelText( strings.fields.phone );

		user.type( firstNameField, 'John' );
		user.type( lastNameField, 'Doe' );
		user.type( emailField, 'john@doe.com' );
		user.type( phoneField, '000000000' );

		expect( firstNameField ).toHaveValue( 'John' );
		expect( lastNameField ).toHaveValue( 'Doe' );
		expect( emailField ).toHaveValue( 'john@doe.com' );
		expect( phoneField ).toHaveValue( '000000000' );
	} );
} );
