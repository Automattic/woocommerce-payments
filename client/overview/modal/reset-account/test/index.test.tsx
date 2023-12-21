/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

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
		render(
			<ResetAccountModal
				isVisible={ true }
				onSubmit={ onSubmit }
				onDismiss={ onDismiss }
			/>
		);

		expect(
			screen.queryByText(
				'If you are experiencing problems completing account setup, or need to change the email/country associated with your account, you can reset your account and start from the beginning.'
			)
		).toBeInTheDocument();
	} );
} );
