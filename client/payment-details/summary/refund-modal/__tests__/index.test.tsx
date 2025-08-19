/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { Charge } from 'wcpay/types/charges';
import RefundModal from '..';
import { usePaymentIntentWithChargeFallback } from 'wcpay/data';
import { ApiError } from 'wcpay/types/errors';

jest.mock( 'wcpay/data', () => ( {
	usePaymentIntentWithChargeFallback: jest.fn(),
} ) );

const mockUsePaymentIntentWithChargeFallback = usePaymentIntentWithChargeFallback as jest.MockedFunction<
	typeof usePaymentIntentWithChargeFallback
>;

describe( 'RefundModal', () => {
	test( 'it renders correctly', () => {
		const charge: any = {
			id: '776',
			amount: 1500,
			amount_captured: 0,
			amount_refunded: 0,
			application_fee_amount: 0,
			balance_transaction: { currency: 'USD', amount: 1500, fee: 0 },
			billing_details: {
				address: {
					city: 'San Francisco',
					country: 'US',
					line1: '60 29th street',
					line2: '',
					postal_code: '91140',
					state: 'CA',
				},
				email: 'admin_test_example@email.com',
				name: 'First Last',
				phone: '20000000000',
				formatted_address: '60 29th street<br/>San Francisco, CA 91140',
			},
			created: 1679922581,
			currency: 'USD',
			disputed: false,
			outcome: null,
			order: {
				number: 776,
				url: 'http://wcpay.test/wp-admin/post.php?post=776&action=edit',
				customer_url:
					'admin.php?page=wc-admin&path=/customers&filter=single_customer&customers=55',
				customer_name: '',
				customer_email: '',
				subscriptions: [],
				fraud_meta_box_type: 'succeeded',
			},
			paid: false,
			paydown: null,
			payment_method: '',
			payment_intent: null,
			payment_method_details: {
				card: { country: 'US', checks: [], network: '' },
				type: 'card' as any,
			},
			refunded: false,
			refunds: null,
			status: 'pending',
		};

		mockUsePaymentIntentWithChargeFallback.mockReturnValue( {
			doRefund: jest.fn(),
			data: charge,
			error: {} as ApiError,
			isLoading: false,
		} );

		const { container: modal } = render(
			<RefundModal
				charge={ charge as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		expect( modal ).toMatchSnapshot();
	} );
} );
