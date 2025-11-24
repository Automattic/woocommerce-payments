/**
 * Internal dependencies
 */
import UserTokenCache from './user-token-cache';

/**
 * Props for the WCPayPaymentMethodElement component
 */
export interface PaymentMethodElementProps {
	element: HTMLSpanElement;
}

/**
 * Token represents a payment method token for a user
 */
export interface Token {
	tokenId: number;
	displayName: string;
}

/**
 * CachedUserDataItem represents cached token data for a specific user
 */
export interface CachedUserDataItem {
	userId: number;
	loading: boolean;
	loadingError: string | null;
	tokens: Token[];
}

/**
 * CachedUserData is an array of cached user token data
 */
export type CachedUserData = CachedUserDataItem[];

/**
 * Props for the PaymentMethodSelect component
 */
export interface PaymentMethodSelectProps {
	inputName: string;
	value: number;
	userId: number;
	cache: UserTokenCache;
	onChange: ( value: number ) => void;
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
