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
import AddBusinessInfoTask from '../';
import { useBusinessTypes } from 'onboarding-experiment/hooks';

declare const global: {
	wcpaySettings: {
		connect: {
			country: string;
		};
	};
};

jest.mock( 'onboarding-experiment/hooks', () => ( {
	useBusinessTypes: jest.fn(),
} ) );

const countries = [
	{
		key: 'US',
		name: 'United States',
		types: [
			{
				key: 'individual',
				name: 'Individual',
				description: 'Individual',
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				description: 'Company',
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
				description: 'Individual',
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				description: 'Company',
				structures: [],
			},
			{
				key: 'non_profit',
				name: 'Non-profit',
				description: 'Non-profit',
				structures: [],
			},
		],
	},
];

const renderTask = () =>
	render( <AddBusinessInfoTask onChange={ jest.fn() } /> );

describe( 'AddBusinessInfoTask', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			connect: {
				country: 'US',
			},
		};
	} );

	it( 'shows a loadable block', () => {
		mocked( useBusinessTypes ).mockReturnValue( {
			countries: [],
			isLoading: true,
		} );

		const { container: task } = renderTask();
		expect( task ).toMatchSnapshot();
	} );

	it( 'shows the form', () => {
		mocked( useBusinessTypes ).mockReturnValue( {
			countries,
			isLoading: false,
		} );

		const { container: task } = renderTask();
		expect( task ).toMatchSnapshot();
	} );

	it( 'shows business type and structure from a selected country', () => {
		global.wcpaySettings = {
			connect: {
				country: 'FR',
			},
		};
		mocked( useBusinessTypes ).mockReturnValue( {
			countries,
			isLoading: false,
		} );

		const { container: task } = renderTask();

		user.click( screen.getByRole( 'button', { name: /country/i } ) );
		user.click(
			screen.getByRole( 'option', {
				name: /united states/i,
			} )
		);

		user.click( screen.getByRole( 'button', { name: /business type/i } ) );
		user.click(
			screen.getByRole( 'option', {
				name: /company/i,
			} )
		);

		user.click(
			screen.getByRole( 'button', { name: /business structure/i } )
		);
		user.click(
			screen.getByRole( 'option', {
				name: /single member llc/i,
			} )
		);

		expect( task ).toMatchSnapshot();
	} );

	it( 'hides the structure when no structures are available', () => {
		global.wcpaySettings = {
			connect: {
				country: 'FR',
			},
		};
		mocked( useBusinessTypes ).mockReturnValue( {
			countries,
			isLoading: false,
		} );

		const { container: task } = renderTask();

		user.click( screen.getByRole( 'button', { name: /country/i } ) );
		user.click(
			screen.getByRole( 'option', {
				name: /united states/i,
			} )
		);

		user.click( screen.getByRole( 'button', { name: /business type/i } ) );
		user.click(
			screen.getByRole( 'option', {
				name: /individual/i,
			} )
		);

		expect( task ).toMatchSnapshot();
		expect(
			screen.queryByRole( 'button', { name: /business structure/i } )
		).not.toBeInTheDocument();
	} );
} );
