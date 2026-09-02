/**
 * External dependencies
 */
import { useMemo } from 'react';
import { useUserPreferences } from '@woocommerce/data';

type UserPreferencesHookResult = ReturnType< typeof useUserPreferences >;

/**
 * Decode a raw `woocommerce_meta` value.
 *
 * Mirrors WooCommerce Admin's own decoding so both sources yield the same shape: values are
 * stored as JSON, except the ones that were written as plain strings such as `yes` or `no`.
 *
 * @param value Raw stored value.
 * @return The decoded value, or an empty string when nothing is stored.
 */
const decodePreference = ( value: string | undefined ): unknown => {
	if ( ! value || value.length === 0 ) {
		return '';
	}

	try {
		return JSON.parse( value );
	} catch ( e ) {
		return value;
	}
};

/**
 * Read the current user's WooPayments preferences.
 *
 * WooCommerce Admin builds the user payload that backs `useUserPreferences` without booting the
 * REST API, so the field holding these preferences is missing on stores where nothing else boots
 * it. Every preference then reads back empty even though it saved correctly, which looks to a
 * merchant like their hidden columns and dismissed notices never stick.
 *
 * Saved values still reach the browser through our own settings, so fall back to those. A live
 * value always wins, which keeps a write made during this page visit ahead of the copy that was
 * rendered with the page.
 *
 * @return The same shape as `useUserPreferences`, with missing values filled in.
 */
export const useWcpayUserPreferences = (): UserPreferencesHookResult => {
	const { isRequesting, updateUserPreferences, ...livePreferences } =
		useUserPreferences();

	const renderedPreferences = useMemo( () => {
		const raw =
			typeof wcpaySettings === 'undefined'
				? undefined
				: wcpaySettings.userPreferences;

		return Object.entries( raw ?? {} ).reduce< Record< string, unknown > >(
			( decoded, [ name, value ] ) => {
				decoded[ name ] = decodePreference( value );
				return decoded;
			},
			{}
		);
	}, [] );

	const preferences = Object.entries(
		livePreferences as Record< string, unknown >
	).reduce< Record< string, unknown > >(
		( merged, [ name, value ] ) => {
			if ( undefined !== value ) {
				merged[ name ] = value;
			}
			return merged;
		},
		{ ...renderedPreferences }
	);

	// The preference names are only known at runtime, so the merged bag can't be inferred.
	return {
		...preferences,
		isRequesting,
		updateUserPreferences,
	} as UserPreferencesHookResult;
};
