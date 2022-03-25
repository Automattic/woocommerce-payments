/** @format */

/**
 * External dependencies
 */
import { render, RenderResult } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import OnboardingSelectControl from '../';

interface OnboardingSelectOption {
	key: string;
	name: string;
	description?: string;
}

function renderOnboardingSelectControl(
	label: string,
	value: unknown = null,
	options: OnboardingSelectOption[]
): RenderResult {
	return render(
		<OnboardingSelectControl
			className="onboarding-select-control"
			label={ label }
			value={ value }
			options={ options }
			describedBy={ 'test' }
			onChange={ undefined }
		/>
	);
}

describe( 'OnboardingSelectControl', () => {
	test( 'renders select control', () => {
		const {
			container: onboardingSelectControl,
		} = renderOnboardingSelectControl(
			'Select favourite fruit:',
			'pineapple',
			[
				{
					key: 'pineapple',
					name: 'Pineapple',
					description: '🍍',
				},
				{
					key: 'mango',
					name: 'Mango',
					description: '🥭',
				},
				{
					key: 'orange',
					name: 'Orange',
					description: '🍊',
				},
			]
		);

		expect( onboardingSelectControl ).toMatchSnapshot();
	} );
} );
