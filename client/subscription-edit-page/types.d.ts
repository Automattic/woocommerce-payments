/**
 * Minimal jQuery type declaration for select2 event handling.
 */
declare global {
	const jQuery: (
		selector: HTMLElement
	) => {
		on: ( event: string, handler: () => void ) => void;
		off: ( event: string, handler: () => void ) => void;
	};
}

/**
 * Token represents a payment method token for a user
 */
export interface Token {
	tokenId: number;
	displayName: string;
	isDefault: boolean;
}

/**
 * Props for the PaymentMethodSelect component
 */
export interface PaymentMethodSelectProps {
	inputName: string;
	initialValue: number;
	initialUserId: number;
	nonce: string;
	ajaxUrl: string;
}

/**
 * Data structure from the wcpayPmSelector dataset attribute
 */
export interface WCPayPMSelectorData {
	value: number;
	userId: number;
	tokens: Token[];
	ajaxUrl: string;
	nonce: string;
}
