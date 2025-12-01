/**
 * Internal dependencies
 */
import type { CachedUserData, CachedUserDataItem, Token } from './types';

/**
 * Add a new entry for a new user in the cache.
 * The new entry can only land in a loading state.
 */
export const startLoading = (
	cachedData: CachedUserData,
	userId: number
): CachedUserData => {
	return [
		...cachedData,
		{
			userId,
			loading: true,
			loadingError: null,
			tokens: [],
		},
	];
};

/**
 * Update the cached data for a user when the tokens are loaded.
 */
export const tokensLoaded = (
	cachedData: CachedUserData,
	userId: number,
	tokens: Token[]
): CachedUserData => {
	return cachedData.map( ( userData ) => {
		if ( userData.userId !== userId ) {
			return userData;
		}

		return {
			...userData,
			tokens,
			loading: false,
			loadingError: null,
		};
	} );
};

/**
 * Update the cached data for a user when loading the tokens for a user failed.
 */
export const loadingFailed = (
	cachedData: CachedUserData,
	userId: number,
	errorMessage: string
): CachedUserData => {
	return cachedData.map( ( userData ) => {
		if ( userData.userId !== userId ) {
			return userData;
		}

		return {
			...userData,
			loading: false,
			loadingError: errorMessage,
		};
	} );
};

/**
 * Check if the cached data for a user contains tokens.
 */
export const hasEntry = (
	cachedData: CachedUserData,
	userId: number
): boolean => {
	return cachedData.some( ( userData ) => userData.userId === userId );
};

/**
 * Get the user entry from the cached data.
 */
export const getUserEntry = (
	cachedData: CachedUserData,
	userId: number
): CachedUserDataItem | undefined => {
	return cachedData.find( ( userData ) => userData.userId === userId );
};

/**
 * Check if a user has a specific token.
 */
export const userHasToken = (
	cachedData: CachedUserData,
	userId: number,
	tokenId: number
): boolean => {
	return ( getUserEntry( cachedData, userId )?.tokens || [] ).some(
		( token ) => token.tokenId === tokenId
	);
};

export const getDefaultTokenId = (
	cachedData: CachedUserData,
	userId: number
): number => {
	const entry = getUserEntry( cachedData, userId );
	const defaultToken = entry?.tokens.find( ( token ) => token.isDefault );
	if ( entry && entry.tokens.length > 0 && defaultToken ) {
		return defaultToken.tokenId;
	}
	return 0;
};
