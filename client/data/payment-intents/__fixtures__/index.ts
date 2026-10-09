/** @format */

/**
 * Internal dependencies
 */
import {
	Charge,
	OutcomeRiskLevel,
	PaymentMethodDetails,
} from '../../../types/charges';
import { PaymentIntent } from '../../../types/payment-intents';

export const chargeId = 'ch_mock';

export const paymentIntentId = 'pi_mock';

export const chargeMock: Charge = {
	id: chargeId,
	amount: 8903,
	created: 1656701170,
	payment_method_details: {
		card: {},
		type: 'card',
	} as unknown as PaymentMethodDetails,
	payment_method: 'pm_mock',
	amount_captured: 8903,
	amount_refunded: 8903,
	application_fee_amount: 82,
	balance_transaction: {
		fee: 82,
		amount: 8903,
		currency: 'usd',
	},
	billing_details: {
		address: {
			city: 'City',
			country: 'US',
			line1: 'Line 1',
			line2: 'Line 2',
			postal_code: 'Postal code',
			state: 'State',
		},
		email: 'admin@example.com',
		name: 'First Name',
		phone: '0-000-000-0000',
		formatted_address: 'Line 1<br/>Line 2<br/>City, State Postal code',
	},
	currency: 'usd',
	dispute: null,
	disputed: false,
	order: {
		id: 123,
		number: 'custom-67',
		url: 'http://order.url',
		customer_url: 'customer.url',
		customer_name: '',
		customer_email: '',
		subscriptions: [],
		ip_address: '127.0.0.1',
	},
	outcome: {
		network_status: 'approved_by_network',
		reason: null,
		risk_level: 'normal' as OutcomeRiskLevel,
		risk_score: 56,
		seller_message: 'Payment complete.',
		type: 'authorized',
	},
	paid: true,
	paydown: null,
	payment_intent: paymentIntentId,
	refunded: true,
	refunds: null,
	status: 'succeeded',
};

export const paymentIntentMock: PaymentIntent = {
	id: paymentIntentId,
	amount: 8903,
	currency: 'USD',
	charge: chargeMock,
	created: 1656701169,
	customer: 'cus_mock',
	metadata: {},
	payment_method: 'pm_mock',
	status: 'requires_capture',
	order: {
		id: 123,
		number: 'custom-123',
		url: 'http://order.url',
		customer_url: 'customer.url',
		customer_name: '',
		customer_email: '',
		fraud_meta_box_type: 'review',
		ip_address: '127.0.0.1',
	},
};
