/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import AddBusinessInfoTask from '../';
import { useBusinessTypes } from '../../../../data';

jest.mock( '../../../../data', () => ( {
	useBusinessTypes: jest.fn(),
} ) );

describe( 'AddBusinessInfoTask', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			connect: {
				country: 'US',
			},
		};

		useBusinessTypes.mockReturnValue( [
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
								key: 'sole_propietorship',
								name: 'Sole propietorship',
							},
							{
								key: 'single_member_llc',
								name: 'Single member llc',
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
		] );
	} );

	it( 'renders page', () => {
		const { container: task } = render( <AddBusinessInfoTask /> );
		expect( task ).toMatchSnapshot();
	} );
} );
