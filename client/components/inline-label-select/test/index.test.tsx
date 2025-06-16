/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import InlineLabelSelect from '..';

interface Item {
	key: string;
	name: string;
	icon?: string;
	hint?: string;
}

const options: Item[] = [
	{
		key: 'EUR',
		name: 'EUR €',
		icon: '💶',
	},
	{
		key: 'JPY',
		name: 'JPY ¥',
		icon: '💴',
		hint: 'Japanese Yen',
	},
	{
		key: 'GBP',
		name: 'GBP £',
		icon: '💷',
		hint: 'British Pound',
	},
];

describe( 'InlineLabelSelect', () => {
	test( 'renders options', () => {
		const { container, getByText } = render(
			<InlineLabelSelect
				className="onboarding-select-control"
				label="Currency"
				value={ options[ 0 ] }
				options={ options }
			/>
		);

		user.click( screen.getByRole( 'button' ) );

		// Option names should be visible.
		getByText( 'JPY ¥' );
		// Hints should be visible.
		getByText( 'British Pound' );

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders options with custom children', () => {
		const { container, getByText } = render(
			<InlineLabelSelect
				className="onboarding-select-control"
				label="Currency"
				value={ undefined }
				placeholder={ 'Currency' }
				options={ options }
				children={ ( item ) => (
					<>
						<span>{ item.icon }</span>
						<span>{ item.name }</span>
					</>
				) }
			/>
		);

		user.click( screen.getByRole( 'button' ) );

		// Option icons should be visible.
		getByText( '💴' );

		user.click( screen.getByRole( 'button' ) );

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with placeholder', () => {
		const { container } = render(
			<InlineLabelSelect
				className="onboarding-select-control"
				label="Currency"
				value={ undefined }
				placeholder={ 'Currency' }
				options={ options }
			/>
		);

		user.click( screen.getByRole( 'button' ) );

		expect( container ).toMatchSnapshot();
	} );
} );
