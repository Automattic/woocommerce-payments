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
import {
	getAvailableCountries,
	getBusinessTypes,
	getMccsFlatList,
} from 'onboarding/utils';

jest.mock( 'onboarding/utils', () => ( {
	getAvailableCountries: jest.fn(),
	getBusinessTypes: jest.fn(),
	getMccsFlatList: jest.fn(),
} ) );

const countries = [
	{
		key: 'ES',
		name: 'Spain',
		types: [],
	},
	{
		key: 'US',
		name: 'United States',
		types: [],
	},
	{
		key: 'FR',
		name: 'France',
		types: [],
	},
];

mocked( getAvailableCountries ).mockReturnValue( countries );

const businessTypes = [
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

mocked( getBusinessTypes ).mockReturnValue( businessTypes );

const mccsFlatList = [
	{
		key: 'most_popular',
		name: 'Most popular',
		items: [
			'most_popular__software_services',
			'most_popular__clothing_and_apparel',
			'most_popular__consulting_services',
		],
	},
	{
		key: 'most_popular__software_services',
		name: 'Popular Software',
		group: 'most_popular',
		context:
			'programming web website design data entry processing integrated systems',
	},
	{
		key: 'most_popular__clothing_and_apparel',
		name: 'Clothing and accessories',
		group: 'most_popular',
		context: '',
	},
	{
		key: 'most_popular__consulting_services',
		name: 'Consulting',
		group: 'most_popular',
		context: '',
	},
	{
		key: 'retail',
		name: 'Retail',
		items: [
			'retail__software',
			'retail__clothing_and_apparel',
			'retail__convenience_stores',
			'retail__beauty_products',
		],
	},
	{
		key: 'retail__software',
		name: 'Software',
		group: 'retail',
		context:
			'app business computer digital electronic hardware lease maintenance personal processing product program programming repair saas sell software retail',
	},
	{
		key: 'retail__clothing_and_apparel',
		name: 'Clothing and accessories',
		group: 'retail',
		context:
			'accessories apparel baby children clothes clothing dress family infant men pant shirt short skirt t-shirt tee undergarment women retail',
	},
	{
		key: 'retail__convenience_stores',
		name: 'Convenience stores',
		group: 'retail',
		context:
			'candy convenience dairy deli delicatessen drink fast food fruit gourmet grocery health market meal poultry preparation produce retail specialty supermarket vegetable vitamin retail',
	},
	{
		key: 'retail__beauty_products',
		name: 'Beauty products',
		group: 'retail',
		context:
			'barber beauty cosmetic make make-up makeup moisture moisturizer retail serum skin skincare treatment up retail',
	},
];

mocked( getMccsFlatList ).mockReturnValue( mccsFlatList );

// describe( 'BusinessDetails', () => {
// 	it( 'renders and updates fields data when they are changed', async () => {
// 		render(
// 			<OnboardingContextProvider>
// 				<BusinessDetails />
// 			</OnboardingContextProvider>
// 		);
// 		const countryField = screen.getByText( strings.fields.country );
//
// 		expect(
// 			screen.queryByText( strings.fields.business_type )
// 		).not.toBeInTheDocument();
// 		expect(
// 			screen.queryByText( strings.fields[ 'company.structure' ] )
// 		).not.toBeInTheDocument();
//
// 		user.click( countryField );
// 		await screen.findByText( 'United States' );
// 		user.click( screen.getByText( 'United States' ) );
//
// 		const businessTypeField = screen.getByText(
// 			strings.fields.business_type
// 		);
// 		user.click( businessTypeField );
// 		await screen.findByText( 'Company' );
// 		user.click( screen.getByText( 'Company' ) );
//
// 		const companyStructureField = screen.getByText(
// 			strings.fields[ 'company.structure' ]
// 		);
//
// 		user.click( companyStructureField );
// 		await screen.findByText( 'Single member LLC' );
// 		user.click( screen.getByText( 'Single member LLC' ) );
//
// 		const mccField = screen.getByText( strings.fields.mcc );
// 		user.click( mccField );
// 		await screen.findByText( 'Popular Software' );
// 		user.click( screen.getByText( 'Popular Software' ) );
//
// 		expect( businessTypeField ).toHaveTextContent( 'Company' );
// 		expect( companyStructureField ).toHaveTextContent(
// 			'Single member LLC'
// 		);
// 		expect( mccField ).toHaveTextContent( 'Popular Software' );
// 	} );
// } );
