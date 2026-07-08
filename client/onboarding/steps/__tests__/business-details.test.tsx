/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import BusinessDetails from '../business-details';
import { OnboardingContextProvider, useOnboardingContext } from '../../context';
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
	{
		key: 'JP',
		name: 'Japan',
		types: [],
	},
];

jest.mocked( getAvailableCountries ).mockReturnValue( countries );

const businessTypes = [
	{
		key: 'ES',
		name: 'Spain',
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
						key: 'nil',
						name: 'None',
					},
				],
			},
		],
	},
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
	{
		key: 'JP',
		name: 'Japan',
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
				requires_structure: false,
				structures: [
					{
						key: 'sole_proprietorship',
						name: 'Sole proprietorship',
					},
				],
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

jest.mocked( getBusinessTypes ).mockReturnValue( businessTypes );

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

jest.mocked( getMccsFlatList ).mockReturnValue( mccsFlatList );

const ContextDataViewer = () => {
	const { data } = useOnboardingContext();

	return (
		<span data-testid="company-structure-value">
			{ data[ 'company.structure' ] }
		</span>
	);
};

const selectBusinessCountry = async ( countryName: string ) => {
	const countryField = screen
		.getByTestId( 'country-select' )
		.querySelector( 'button' );

	if ( ! countryField ) {
		throw new Error( 'Country select not found' );
	}

	await user.click( countryField );
	await screen.findByText( countryName );
	await user.click( screen.getByText( countryName ) );
};

const selectBusinessType = async ( businessTypeName: string ) => {
	const businessTypeField = screen
		.getByTestId( 'business-type-select' )
		.querySelector( 'button' );

	if ( ! businessTypeField ) {
		throw new Error( 'Business type select not found' );
	}

	await user.click( businessTypeField );
	await screen.findByText( businessTypeName );
	await user.click( screen.getByText( businessTypeName ) );

	return businessTypeField;
};

const selectBusinessStructure = async ( businessStructureName: string ) => {
	const companyStructureField = screen
		.getByTestId( 'business-structure-select' )
		.querySelector( 'button' );

	if ( ! companyStructureField ) {
		throw new Error( 'Company structure select not found' );
	}

	await user.click( companyStructureField );
	await screen.findByText( businessStructureName );
	await user.click( screen.getByText( businessStructureName ) );

	return companyStructureField;
};

describe( 'BusinessDetails', () => {
	beforeEach( () => {
		jest.mocked( getBusinessTypes ).mockReturnValue( businessTypes );
	} );

	it( 'renders and updates fields data when they are changed', async () => {
		render(
			<OnboardingContextProvider>
				<BusinessDetails />
			</OnboardingContextProvider>
		);
		await selectBusinessCountry( 'United States' );
		const businessTypeField = await selectBusinessType( 'Company' );
		const companyStructureField =
			await selectBusinessStructure( 'Single member LLC' );

		const mccField = screen
			.getByTestId( 'mcc-select' )
			.querySelector( 'button' );
		if ( ! mccField ) {
			throw new Error( 'MCC select not found' );
		}

		await user.click( mccField );
		await screen.findByText( 'Popular Software' );
		await user.click( screen.getByText( 'Popular Software' ) );

		expect( businessTypeField ).toHaveTextContent( 'Company' );
		expect( companyStructureField ).toHaveTextContent(
			'Single member LLC'
		);
		expect( mccField ).toHaveTextContent( 'Popular Software' );
	} );

	it( 'continues without showing business structure when it is optional', async () => {
		render(
			<OnboardingContextProvider>
				<BusinessDetails />
			</OnboardingContextProvider>
		);

		await selectBusinessCountry( 'Japan' );
		await selectBusinessType( 'Company' );

		expect(
			screen.queryByTestId( 'business-structure-select' )
		).not.toBeInTheDocument();
		expect( screen.getByTestId( 'mcc-select' ) ).toBeInTheDocument();
		expect(
			screen.getByText( /By using WooPayments/ )
		).toBeInTheDocument();
	} );

	it( 'continues without showing business structure when the only structure is nil', async () => {
		render(
			<OnboardingContextProvider>
				<BusinessDetails />
			</OnboardingContextProvider>
		);

		await selectBusinessCountry( 'Spain' );
		await selectBusinessType( 'Company' );

		expect(
			screen.queryByTestId( 'business-structure-select' )
		).not.toBeInTheDocument();
		expect( screen.getByTestId( 'mcc-select' ) ).toBeInTheDocument();
		expect(
			screen.getByText( /By using WooPayments/ )
		).toBeInTheDocument();
	} );

	it( 'clears stale company structure when the structure field becomes hidden', async () => {
		const optionalStructureBusinessTypes = businessTypes.map(
			( country ) =>
				country.key === 'US'
					? {
							...country,
							types: country.types.map( ( type ) =>
								type.key === 'company'
									? { ...type, requires_structure: false }
									: type
							),
					  }
					: country
		);

		const { rerender } = render(
			<OnboardingContextProvider>
				<BusinessDetails />
				<ContextDataViewer />
			</OnboardingContextProvider>
		);

		await selectBusinessCountry( 'United States' );
		await selectBusinessType( 'Company' );
		await selectBusinessStructure( 'Single member LLC' );
		expect(
			screen.getByTestId( 'company-structure-value' )
		).toHaveTextContent( 'single_member_llc' );

		jest.mocked( getBusinessTypes ).mockReturnValue(
			optionalStructureBusinessTypes
		);

		rerender(
			<OnboardingContextProvider>
				<BusinessDetails />
				<ContextDataViewer />
			</OnboardingContextProvider>
		);

		expect(
			screen.queryByTestId( 'business-structure-select' )
		).not.toBeInTheDocument();
		await waitFor( () =>
			expect(
				screen.getByTestId( 'company-structure-value' )
			).toHaveTextContent( /^$/ )
		);
	} );
} );
