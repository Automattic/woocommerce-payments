/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { Dispute } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';

export interface ExtendedDispute
	extends Omit< Dispute, 'evidence' | 'charge' > {
	merchant_name?: string;
	merchant_address?: string;
	merchant_email?: string;
	merchant_phone?: string;
	evidence: {
		[ key: string ]:
			| string
			| Record< string, boolean >
			| Record< string, string >;
	};
	charge: Charge;
}

export interface AccountDetails {
	name: string;
	support_address_city: string;
	support_address_country: string;
	support_address_line1: string;
	support_address_line2: string;
	support_address_postal_code: string;
	support_address_state: string;
}

export interface CoverLetterData {
	merchantAddress: string;
	merchantName: string;
	merchantEmail: string;
	merchantPhone: string;
	today: string;
	acquiringBank: string;
	caseNumber: string;
	transactionId: string;
	transactionDate: string;
	customerName: string;
	product: string;
	orderDate: string;
	deliveryDate: string;
}

export interface CoverLetterProps {
	value: string;
	onChange: ( value: string ) => void;
	readOnly?: boolean;
}
