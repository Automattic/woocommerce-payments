/**
 * pnpm hook to fix peer dependency resolution issues.
 *
 * The packages below peer-depend on @wordpress/data ^10.x, but the root pins
 * 6.6.1. pnpm satisfies a peer from the consumer, so it hands them 6.6.1, which
 * their 10.x-compiled code can't use (`createReduxStore is not a function`) and
 * which `jest.mock('@wordpress/data')` swaps out for every importer at once.
 * Converting the peer to a dependency gives each its own copy, as npm's nesting
 * did.
 *
 * Tech debt: the allowlist is hardcoded, so the next package with this mismatch
 * fails the same opaque way — the warning below is the only signpost back here.
 * Drop all of it once the repo moves off @wordpress/data 6.6.1.
 *
 * Careful: @wordpress/private-apis needs the opposite — one shared instance, or
 * its registry throws `Cannot unlock an object that was not locked before`. npm
 * hoists it to a single copy; pnpm doesn't, so the `@wordpress/dataviews>*` pins
 * in package.json are load-bearing. A copy per package is the failure, not the
 * fix.
 */
const PACKAGES_NEEDING_OWN_WP_DATA = [
	'@woocommerce/components',
	'@woocommerce/data',
];

// Root pin. A peer range whose lowest major exceeds this can't be satisfied by
// the root copy, so such a package is a candidate for the treatment above.
const ROOT_WP_DATA_MAJOR = 6;

function readPackage( pkg ) {
	const peerWpData =
		pkg.peerDependencies && pkg.peerDependencies[ '@wordpress/data' ];

	if ( ! peerWpData ) {
		return pkg;
	}

	if ( PACKAGES_NEEDING_OWN_WP_DATA.includes( pkg.name ) ) {
		pkg.dependencies = pkg.dependencies || {};
		pkg.dependencies[ '@wordpress/data' ] = peerWpData;
		delete pkg.peerDependencies[ '@wordpress/data' ];
		return pkg;
	}

	// Not covered by the allowlist: warn if it looks like it should be, so the
	// next mismatch surfaces here instead of as an opaque Jest failure.
	const lowestMajor = Number( ( peerWpData.match( /(\d+)/ ) || [] )[ 1 ] );
	if (
		/^@woocommerce\//.test( pkg.name ) &&
		lowestMajor > ROOT_WP_DATA_MAJOR
	) {
		console.warn(
			`[.pnpmfile.cjs] ${ pkg.name } peer-depends on @wordpress/data ` +
				`"${ peerWpData }", which the root ${ ROOT_WP_DATA_MAJOR }.x pin ` +
				`cannot satisfy, but it is not in PACKAGES_NEEDING_OWN_WP_DATA. ` +
				`If Jest fails with "createReduxStore is not a function", add it there.`
		);
	}

	return pkg;
}

module.exports = {
	hooks: {
		readPackage,
	},
};
