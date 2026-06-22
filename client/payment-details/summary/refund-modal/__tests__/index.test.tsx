/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { Charge } from 'wcpay/types/charges';
import RefundModal from '..';
import { usePaymentIntentWithChargeFallback } from 'wcpay/data/payment-intents';
import { ApiError } from 'wcpay/types/errors';
import { recordEvent } from 'tracks';

jest.mock( 'wcpay/data/payment-intents', () => ( {
	usePaymentIntentWithChargeFallback: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const getOpenInquiryDispute = (): any => ( {
	id: 'dp_inquiry_1',
	status: 'warning_needs_response',
	reason: 'fraudulent',
} );

const mockUsePaymentIntentWithChargeFallback =
	usePaymentIntentWithChargeFallback as jest.MockedFunction<
		typeof usePaymentIntentWithChargeFallback
	>;

const getMockCharge = (): any => ( {
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
} );

describe( 'RefundModal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockUsePaymentIntentWithChargeFallback.mockReturnValue( {
			doRefund: jest.fn(),
			data: getMockCharge(),
			error: {} as ApiError,
			isLoading: false,
		} );
	} );

	test( 'it renders correctly', () => {
		const { container: modal } = render(
			<RefundModal
				charge={ getMockCharge() as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		expect( modal ).toMatchSnapshot();
	} );

	test( 'offers a link to the order when orderUrl is provided', () => {
		const charge = getMockCharge();

		render(
			<RefundModal
				charge={ charge as Charge }
				formattedAmount={ 'USD 15' }
				orderUrl={ charge.order.url }
				onModalClose={ jest.fn() }
			/>
		);

		const orderLink = screen.getByRole( 'link', {
			name: /Go to the order/i,
		} );

		expect( orderLink ).toHaveAttribute( 'href', charge.order.url );
	} );

	test( 'does not offer the order link when orderUrl is omitted', () => {
		render(
			<RefundModal
				charge={ getMockCharge() as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		expect(
			screen.queryByRole( 'link', { name: /Go to the order/i } )
		).not.toBeInTheDocument();
	} );

	test( 'explains the inquiry will be closed when the charge has an open inquiry', () => {
		const charge = { ...getMockCharge(), dispute: getOpenInquiryDispute() };

		render(
			<RefundModal
				charge={ charge as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		expect(
			screen.getByText( /Issuing a refund will close the inquiry/i )
		).toBeInTheDocument();
	} );

	test( 'does not mention the inquiry for a charge without an open inquiry', () => {
		render(
			<RefundModal
				charge={ getMockCharge() as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		expect(
			screen.queryByText( /Issuing a refund will close the inquiry/i )
		).not.toBeInTheDocument();
	} );

	test( 'fires the inquiry refund tracks event when refunding an open inquiry', async () => {
		const dispute = getOpenInquiryDispute();
		const charge = { ...getMockCharge(), dispute };

		render(
			<RefundModal
				charge={ charge as Charge }
				formattedAmount={ 'USD 15' }
				onModalClose={ jest.fn() }
			/>
		);

		// Wrap in act so the refund promise and its trailing state updates flush.
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'button', { name: /Refund transaction/i } )
			);
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_inquiry_refund_click',
			{
				dispute_id: dispute.id,
				dispute_status: dispute.status,
				dispute_reason: dispute.reason,
				on_page: 'transaction_details',
			}
		);
	} );
} );
