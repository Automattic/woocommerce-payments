/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CompanyRequirements from '../company';

const baseKeys = [
	'company.name',
	'company.address.line1',
	'company.tax_id',
	'representative.first_name',
	'representative.dob.day',
	'representative.address.line1',
];

describe( 'Onboarding Requirements Company', () => {
	test( 'renders without company details', () => {
		const { container: companyRequirements } = render(
			<CompanyRequirements keys={ baseKeys } />
		);
		expect( companyRequirements ).toMatchSnapshot();
	} );

	test( 'renders with owners', () => {
		const keys = [ ...baseKeys, 'owners.first_name' ];

		const { container: companyRequirements } = render(
			<CompanyRequirements keys={ keys } />
		);
		expect( companyRequirements ).toMatchSnapshot();
	} );

	test( 'renders with directors and executives', () => {
		const keys = [
			...baseKeys,
			'directors.first_name',
			'executives.email',
		];

		const { container: companyRequirements } = render(
			<CompanyRequirements keys={ keys } />
		);
		expect( companyRequirements ).toMatchSnapshot();
	} );
} );
