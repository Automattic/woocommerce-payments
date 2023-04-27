/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { mocked } from 'ts-jest/utils';

/**
 * Internal dependencies
 */
import BusinessDetails from '../business-details';
import { OnboardingContextProvider } from '../../context';
import strings from '../../strings';
import { getBusinessTypes } from 'onboarding-experiment/utils';

jest.mock( 'onboarding-experiment/utils', () => ( {
	getBusinessTypes: jest.fn(),
} ) );

const countries = [
	{
		key: 'US',
		name: 'United States',
		types: [
			{
				key: 'individual',
				name: 'Individual',
				description: 'Individual description',
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				description: 'Company description',
				structures: [
					{
						key: 'sole_proprietorship',
						name: 'Sole proprietorship',
					},
					{
						key: 'single_member_llc',
						name: 'Single member LLC',
					},
				],
			},
		],
	},
	{
		key: 'FR',
		name: 'France',
		types: [
			{
				key: 'individual',
				name: 'Individual',
				description: 'Individual description',
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				description: 'Company description',
				structures: [],
			},
			{
				key: 'non_profit',
				name: 'Non-profit',
				description: 'Non-profit description',
				structures: [],
			},
		],
	},
];

mocked( getBusinessTypes ).mockReturnValue( countries );

describe( 'BusinessDetails', () => {
	it( 'renders and updates fields data when they are changed', () => {
		render(
			<OnboardingContextProvider>
				<BusinessDetails />
			</OnboardingContextProvider>
		);
		const businessNameField = screen.getByLabelText(
			strings.fields.business_name
		);
		const urlField = screen.getByLabelText( strings.fields.url );
		const countryField = screen.getByText( strings.placeholders.country );

		user.type( businessNameField, 'John Doe LLC' );
		user.type( urlField, 'https://johndoe.com' );

		expect(
			screen.queryByText( strings.placeholders.business_type )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( strings.placeholders[ 'company.structure' ] )
		).not.toBeInTheDocument();

		user.click( countryField );
		user.click( screen.getByText( 'United States' ) );

		const businessTypeField = screen.getByText(
			strings.placeholders.business_type
		);
		user.click( businessTypeField );
		user.click( screen.getByText( 'Company' ) );

		const companyStructureField = screen.getByText(
			strings.placeholders[ 'company.structure' ]
		);

		user.click( companyStructureField );
		user.click( screen.getByText( 'Single member LLC' ) );

		// TODO  [GH-4853]: Add mcc field test

		expect( businessNameField ).toHaveValue( 'John Doe LLC' );
		expect( urlField ).toHaveValue( 'https://johndoe.com' );
		expect( countryField ).toHaveTextContent( 'United States' );
		expect( businessTypeField ).toHaveTextContent( 'Company' );
		expect( companyStructureField ).toHaveTextContent(
			'Single member LLC'
		);
	} );
} );
