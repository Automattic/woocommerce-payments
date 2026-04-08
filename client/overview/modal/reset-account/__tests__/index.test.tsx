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
				'When you reset your account, all payment data — including your WooPayments account details, test transactions, and payouts history — will be lost. Your order history will remain. This action cannot be undone, but you can create a new test account at any time.'
			)
		).toBeInTheDocument();
	} );
} );
