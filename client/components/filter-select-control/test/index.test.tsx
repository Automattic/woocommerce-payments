/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, RenderResult, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import FilterSelectControl from '..';

interface Item {
	key: string;
	name: string;
	icon: string;
}

const options = [
	{
		key: 'EUR',
		name: 'EUR €',
		icon: '💶',
	},
	{
		key: 'JPY',
		name: 'JPY ¥',
		icon: '💴',
	},
	{
		key: 'GBP £',
		name: 'GBP £',
		icon: '💷',
	},
];

function renderFilterSelectControl(
	placeholder?: string,
	children?: ( item: Item ) => JSX.Element
): RenderResult {
	return render(
		<FilterSelectControl
			className="onboarding-select-control"
			label="Currency"
			value={ placeholder ? undefined : options[ 0 ] }
			placeholder={ placeholder }
			options={ options }
			children={ children }
		/>
	);
}

describe( 'FilterSelectControl', () => {
	test( 'renders options', () => {
		const { container } = renderFilterSelectControl();

		user.click( screen.getByRole( 'button' ) );

		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'FilterSelectControl', () => {
	test( 'renders options with custom children', () => {
		const { container } = renderFilterSelectControl(
			undefined,
			( item ) => (
				<>
					<span>{ item.icon }</span>
					<span>{ item.name }</span>
				</>
			)
		);

		user.click( screen.getByRole( 'button' ) );

		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'FilterSelectControl', () => {
	test( 'renders with placeholder', () => {
		const { container } = renderFilterSelectControl( 'Currency' );

		expect( container ).toMatchSnapshot();
	} );
} );
