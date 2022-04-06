/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import React from 'react';

/**
 * Internal dependencies
 */
import AddBusinessInfoTask from '../';
import { useBusinessTypes } from 'wcpay/data/onboarding';

jest.mock( 'wcpay/data/onboarding', () => ( {
	useBusinessTypes: jest.fn(),
} ) );

const businessTypes = [
	{
		key: 'US',
		name: 'United States',
		types: [
			{
				key: 'individual',
				name: 'Individual',
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				structures: [
					{
						key: 'sole_proprietorship',
						name: 'Sole propietorship',
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
				structures: [],
			},
			{
				key: 'company',
				name: 'Company',
				structures: [],
			},
			{
				key: 'non_profit',
				name: 'Non-profit',
				structures: [],
			},
		],
	},
];

const renderTask = () =>
	render( <AddBusinessInfoTask onChange={ () => {} } /> );

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
		useBusinessTypes.mockReturnValue( {
			businessTypes: [],
			isLoading: true,
		} );

		const { container: task } = renderTask();
		expect( task ).toMatchSnapshot();
	} );

	it( 'shows the form', () => {
		useBusinessTypes.mockReturnValue( {
			businessTypes,
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
		useBusinessTypes.mockReturnValue( {
			businessTypes,
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
		useBusinessTypes.mockReturnValue( {
			businessTypes,
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
