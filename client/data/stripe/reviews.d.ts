/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { PaymentIntent } from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { Charge } from './charges';

type ClosedReason = 'approved' | 'disputed' | 'refunded' | 'refunded_as_fraud';

interface IpAddressLocation {
	city: string | null;
	country: string | null;
	latitude: number | null;
	longitude: number | null;
	region: string | null;
}

type OpenedReason = 'manual' | 'rule';

interface Session {
	browser: string | null;
	device: string | null;
	platform: string | null;
	version: string | null;
}

interface Review {
	id: string;
	object: 'review';
	billing_zip: string | null;
	charge: string | Charge | null;
	closed_reason: ClosedReason | null;
	created: number;
	ip_address: string | null;
	ip_address_location: IpAddressLocation | null;
	livemode: boolean;
	open: boolean;
	opened_reason: OpenedReason;
	payment_intent?: string | PaymentIntent;
	reason: string;
	session: Session | null;
}
