/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { chargeMock } from 'wcpay/data/payment-intents/test/hooks';

/**
 * Internal dependencies
 */
import MissingOrderNotice from '..';
import { Charge } from 'wcpay/types/charges';

describe( 'MissingOrderNotice', () => {
	test( 'it renders correctly', () => {
		const { container: notice } = render(
			<MissingOrderNotice
				charge={ chargeMock }
				isLoading={ false }
				onButtonClick={ jest.fn() }
			/>
		);

		expect( notice ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const { container: notice } = render(
			<MissingOrderNotice
				charge={ {} as Charge }
				isLoading={ true }
				onButtonClick={ jest.fn }
			/>
		);
		expect( notice ).toMatchSnapshot();
	} );
} );
