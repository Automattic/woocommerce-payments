/* eslint-disable camelcase */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import type { ApiList } from './apilist';
import type { StripeMetadata } from './metadata';

interface FileLink {
	id: string;
	object: 'file_link';
	created: number;
	expired: boolean;
	expires_at: number | null;
	file: string | File;
	livemode: boolean;
	metadata: StripeMetadata;
	url: string | null;
}

interface File {
	id: string;
	object: 'file';
	created: number;
	expires_at: number | null;
	filename: string | null;
	links?: ApiList< FileLink > | null;
	purpose:
		| 'account_requirement'
		| 'additional_verification'
		| 'business_icon'
		| 'business_logo'
		| 'customer_signature'
		| 'dispute_evidence'
		| 'identity_document'
		| 'pci_document'
		| 'tax_document_user_upload';
	size: number;
	title: string | null;
	type: string | null;
	url: string | null;
}
