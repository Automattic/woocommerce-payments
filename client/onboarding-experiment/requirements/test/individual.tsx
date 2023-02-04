/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import IndividualRequirements from '../individual';

describe( 'Onboarding Requirements Individual', () => {
	test( 'renders personal details', () => {
		const keys = [
			'individual.first_name',
			'individual.dob.day',
			'individual.address.line1',
			'individual.email',
		];

		const { container: individualRequirements } = render(
			<IndividualRequirements keys={ keys } />
		);
		expect( individualRequirements ).toMatchSnapshot();
	} );

	test( 'renders with tax', () => {
		const keys = [
			'individual.first_name',
			'individual.dob.day',
			'individual.address.line1',
			'individual.id_number',
		];

		const { container: individualRequirements } = render(
			<IndividualRequirements keys={ keys } />
		);
		expect( individualRequirements ).toMatchSnapshot();
	} );
} );
