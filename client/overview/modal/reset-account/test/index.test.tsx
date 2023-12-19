/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ResetAccountModal from '..';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

const onSubmit = jest.fn();
const onDismiss = jest.fn();

describe( 'Reset Account Modal', () => {
	it( 'modal is open when is visible is true', () => {
		const { container } = render(
			<ResetAccountModal
				isVisible={ true }
				onSubmit={ onSubmit }
				onDismiss={ onDismiss }
			/>
		);

		expect( container ).toMatchSnapshot();
	} );
} );
