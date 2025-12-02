/**
 * Token represents a payment method token for a user
 */
export interface Token {
	tokenId: number;
	displayName: string;
	isDefault: boolean;
}

/**
 * CachedUserDataItem represents cached token data for a specific user
 */
export interface CachedUserDataItem {
	loading: boolean;
	loadingError: string | null;
	tokens: Token[];
}

/**
 * CachedUserData is a map of user IDs to their cached token data
 */
export type CachedUserData = Record< number, CachedUserDataItem >;

/**
 * Props for the PaymentMethodSelect component
 */
export interface PaymentMethodSelectProps {
	inputName: string;
	initialValue: number;
	initialUserId: number;
	initialCache: CachedUserData;
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

/**
 * Response structure from the fetchUserTokens API call
 */
export interface FetchUserTokensResponse {
	tokens: Token[];
}
