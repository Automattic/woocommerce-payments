/**
 * External dependencies
 */
import { getQuery } from '@woocommerce/navigation';

/**
 * Prefix of the flags forwarded by the stable settings link
 * (`{home_url}/?woopayments-settings-redirect&<flag>=<value>`).
 * Mirrors `SettingsRedirectService::FLAG_PREFIX`.
 */
export const settingsRedirectFlagPrefix = 'woopayments-flag-';

/**
 * Returns the flags passed to the settings page through the stable settings link, keyed by
 * their unprefixed name. A flag passed without a value reads as `'1'`.
 *
 * Anyone can craft the link, so flags may only select what the page shows, never change state.
 *
 * @param query Query args to read; defaults to the current page's.
 * @return Flags keyed by name.
 */
export const getSettingsRedirectFlags = (
	query: Record< string, unknown > = getQuery() as Record< string, unknown >
): Record< string, string > =>
	Object.entries( query ).reduce< Record< string, string > >(
		( flags, [ key, value ] ) => {
			if (
				key.startsWith( settingsRedirectFlagPrefix ) &&
				key.length > settingsRedirectFlagPrefix.length &&
				typeof value === 'string'
			) {
				flags[ key.slice( settingsRedirectFlagPrefix.length ) ] = value;
			}
			return flags;
		},
		{}
	);
