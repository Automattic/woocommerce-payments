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
 * their unprefixed name. The server sends `'1'` for a flag passed without a value.
 *
 * Flags are an untrusted input. Nothing should change state based on the flags. Values are sanitized text of at most
 * 100 characters, but can still read like a URL, so never use one as a link target or markup; only compare it with
 * known values. Any person can create the link and
 * zero-trust policy should apply here. The returned object has no prototype, so names like `constructor` or
 * `hasOwnProperty` are plain flags.
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
		Object.create( null )
	);
