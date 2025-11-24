/**
 * Internal dependencies
 */
import type { CachedUserData, CachedUserDataItem, Token } from './types';

export default class UserTokenCache {
	private cache: CachedUserData = [];
	private callbacks: ( () => void )[] = [];

	public subscribe( callback: () => void ) {
		this.callbacks.push( callback );
	}

	public getCache(): CachedUserData {
		return this.cache;
	}

	private updateCache(): void {
		this.cache = [ ...this.cache ];
		this.callbacks.forEach( ( callback ) => callback() );
	}

	/**
	 * Generates the initial user-token cache in a proper format.
	 *
	 * @param userId Initial user ID.
	 * @param tokens The pre-loaded tokens.
	 */
	public add( userId: number, tokens: Token[] ): void {
		this.cache.push( {
			userId,
			tokens,
			loading: false,
			loadingError: null,
		} );
		this.updateCache();
	}

	/**
	 * Add a new entry for a new user in the cache.
	 * The new entry can only land in a loading state.
	 *
	 * @param userId The user ID.
	 */
	public startLoading( userId: number ): void {
		this.cache.push( {
			userId,
			loading: true,
			loadingError: null,
			tokens: [],
		} );
		this.updateCache();
	}

	/**
	 * Update the cached data for a user when the tokens are loaded.
	 *
	 * @param userId The user ID.
	 * @param tokens The loaded tokens.
	 */
	public tokensLoaded( userId: number, tokens: Token[] ): void {
		this.cache = this.cache.map( ( userData ) => {
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
		this.updateCache();
	}

	/**
	 * Update the cached data for a user when loading the tokens for a user failed.
	 *
	 * @param userId The user ID.
	 * @param errorMessage The error message.
	 */
	public loadingFailed( userId: number, errorMessage: string ): void {
		this.cache = this.cache.map( ( userData ) => {
			if ( userData.userId !== userId ) {
				return userData;
			}

			return {
				...userData,
				loading: false,
				loadingError: errorMessage,
			};
		} );
		this.updateCache();
	}

	/**
	 * Check if the cached data for a user contains tokens.
	 *
	 * @param userId The user ID.
	 * @return True if the cached data for the user contains tokens, false otherwise.
	 */
	public hasEntry( userId: number ): boolean {
		return this.cache.some( ( userData ) => userData.userId === userId );
	}

	/**
	 * Get the user entry from the cached data.
	 *
	 * @param userId The user ID.
	 * @return The user entry.
	 */
	public getUserEntry = (
		userId: number
	): CachedUserDataItem | undefined => {
		return this.cache.find( ( userData ) => userData.userId === userId );
	};

	/**
	 * Check if a user has a specific token.
	 *
	 * @param userId The user ID.
	 * @param tokenId The token ID.
	 * @return True if the user has the token, false otherwise.
	 */
	public userHasToken = ( userId: number, tokenId: number ): boolean => {
		return (
			this.getUserEntry( userId )?.tokens.some(
				( token ) => token.tokenId === tokenId
			) ?? false
		);
	};
}
