/**
 * External dependencies
 */
import type { Address, PaymentMethod, PaymentIntent } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import type { ApiList, StripeMetadata } from 'data/stripe';

interface CustomField {
	name: string;
	value: string;
}

interface InvoiceSettings {
	// eslint-disable-next-line camelcase
	custom_fields: Array< CustomField > | null;
	// eslint-disable-next-line camelcase
	default_payment_method: string | PaymentMethod | null;
	footer: string | null;
}

type TaxExempt = 'exempt' | 'none' | 'reverse';

export interface Customer {
	id: string;
	object: 'customer';
	address: Address | null;
	balance: number;
	created: number;
	currency: string | null;
	// eslint-disable-next-line camelcase
	default_source: string | unknown | null;
	deleted?: void;
	delinquent: boolean | null;
	description: string | null;
	discount: unknown | null;
	email: string | null;
	// eslint-disable-next-line camelcase
	invoice_prefix: string | null;
	// eslint-disable-next-line camelcase
	invoice_settings: InvoiceSettings;
	livemode: boolean;
	metadata: StripeMetadata;
	name: string | null;
	// eslint-disable-next-line camelcase
	next_invoice_sequence?: number;
	phone: string | null;
	// eslint-disable-next-line camelcase
	preferred_locales: Array< string > | null;
	shipping: PaymentIntent.Shipping | null;
	sources?: ApiList< unknown >;
	subscriptions?: ApiList< unknown >;
	// eslint-disable-next-line camelcase
	tax_exempt?: TaxExempt | null;
	// eslint-disable-next-line camelcase
	tax_ids: ApiList< unknown >;
}

export interface DeletedCustomer {
	id: string;
	object: 'customer';
	deleted: true;
}
