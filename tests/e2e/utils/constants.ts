/**
 * External dependencies
 */
import semver from 'semver';

export const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

export const shouldRunActionSchedulerTests =
	process.env.SKIP_WC_ACTION_SCHEDULER_TESTS !== '1';

export const shouldRunWCBlocksTests = process.env.SKIP_WC_BLOCKS_TESTS !== '1';

export const wooCoreVersion = process.env.E2E_WC_VERSION;

/**
 * Compares WooCommerce version strings.
 * Returns true if the current version is greater than or equal to the target version.
 * Handles semantic versions (e.g., "10.5.0") and special values ("latest", "beta").
 *
 * @param targetVersion - The version to compare against (e.g., "10.5.0")
 * @return true if wooCoreVersion >= targetVersion
 */
export const isWooCommerceVersionAtLeast = (
	targetVersion: string
): boolean => {
	const currentVersion = wooCoreVersion;

	// If no version is set, assume latest
	if ( ! currentVersion ) {
		return true;
	}

	// "latest" and "beta" are assumed to be newer than any specific version
	if ( currentVersion === 'latest' || currentVersion === 'beta' ) {
		return true;
	}

	// Use semver to compare versions, coercing if needed for partial versions
	const current = semver.coerce( currentVersion );
	const target = semver.coerce( targetVersion );

	if ( ! current || ! target ) {
		// If we can't parse either version, assume the current version is newer
		return true;
	}

	return semver.gte( current, target );
};

export const isAtomicSite = process.env.NODE_ENV === 'atomic';

export const performanceReportDir = __dirname + '/../reports/';

export const performanceReportfilename =
	performanceReportDir + 'checkout-performance.txt';

export const performanceNumberOfTrials = 3;
