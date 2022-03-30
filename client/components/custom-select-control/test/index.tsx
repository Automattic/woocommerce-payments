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
import _CustomSelectControl from '../';

interface Item {
	key: string;
	name: string;
	icon: string;
}

const CustomSelectControl = _CustomSelectControl as React.FunctionComponent< {
	className?: string;
	label?: string;
	options: Item[];
	value?: Item | string;
	placeholder?: string;
	children?: ( item: Item ) => JSX.Element;
} >;

const options = [
	{
		key: 'pineapple',
		name: 'Pineapple',
		icon: '🍍',
	},
	{
		key: 'mango',
		name: 'Mango',
		icon: '🥭',
	},
	{
		key: 'orange',
		name: 'Orange',
		icon: '🍊',
	},
];

function renderCustomSelectControl(
	placeholder?: string,
	children?: ( item: Item ) => JSX.Element
): RenderResult {
	return render(
		<CustomSelectControl
			className="onboarding-select-control"
			label="Favourite fruit"
			value={ placeholder ? '' : options[ 0 ] }
			placeholder={ placeholder }
			options={ options }
			children={ children }
		/>
	);
}

describe( 'CustomSelectControl', () => {
	test( 'renders options', () => {
		const {
			container: onboardingSelectControl,
		} = renderCustomSelectControl();

		user.click( screen.getByRole( 'button' ) );

		expect( onboardingSelectControl ).toMatchSnapshot();
	} );
} );

describe( 'CustomSelectControl', () => {
	test( 'renders options with custom children', () => {
		const {
			container: onboardingSelectControl,
		} = renderCustomSelectControl( undefined, ( item ) => (
			<>
				<span>{ item.icon }</span>
				<span>{ item.name }</span>
			</>
		) );

		user.click( screen.getByRole( 'button' ) );

		expect( onboardingSelectControl ).toMatchSnapshot();
	} );
} );

describe( 'CustomSelectControl', () => {
	test( 'renders with placeholder', () => {
		const {
			container: onboardingSelectControl,
		} = renderCustomSelectControl( 'Which fruit do you like best?' );

		expect( onboardingSelectControl ).toMatchSnapshot();
	} );
} );
