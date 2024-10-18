/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentDataHighlight from '../';

describe( 'PaymentDataHighlight', () => {
	test( 'renders PaymentDataHighlight with tooltip', () => {
		const { container: data } = render(
			<PaymentDataHighlight
				label="Deposits"
				amount={ '€3,143.00' }
				change={ 22 }
				reportUrl="#"
				tooltip={ __(
					"A charge is the amount billed to your customer's payment method.",
					'woocommerce-payments'
				) }
			/>
		);

		expect( data ).toMatchSnapshot();
	} );

	test( 'renders PaymentDataHighlight without tooltip', () => {
		const { container: data } = render(
			<PaymentDataHighlight
				label="Transactions"
				amount={ '€2,143.00' }
				change={ 22 }
				reportUrl="#"
			/>
		);

		expect( data ).toMatchSnapshot();
	} );
} );
