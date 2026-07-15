/**
 * pnpm hook to fix peer dependency resolution issues.
 *
 * A few @woocommerce/* packages declare `@wordpress/data` as a peer. With pnpm,
 * a peer resolves to whatever the consumer provides. This repo already runs two
 * @wordpress/data majors side by side: the root dependency pins 6.6.1, while
 * `pnpm.overrides` in package.json forces `@wordpress/dataviews>@wordpress/data`
 * to 10.46.0. The @woocommerce/* packages below are built against the ^10.x
 * line, which 6.6.1 does NOT satisfy, so pnpm handing them the root 6.6.1
 * breaks them two ways in Jest:
 *   - @woocommerce/data's build calls `createReduxStore`, which the 6.6.1 module
 *     it gets handed doesn't expose the way its 10.x-compiled code expects.
 *   - `jest.mock('@wordpress/data')` replaces the shared module for every
 *     importer, including packages that need the real implementation.
 *
 * Converting the peer to a regular dependency makes pnpm install each package
 * its own @wordpress/data copy (at the version its peer range asks for), which
 * matches npm's nested resolution and keeps those copies out of the mock.
 *
 * KNOWN LIMITATION (tech debt): this is a hardcoded allowlist. A future
 * @woocommerce/* package with the same peer mismatch won't be covered and will
 * fail with the same opaque `createReduxStore is not a function` error, with
 * nothing pointing back here. The warning below turns that silent rot into a
 * loud install-time signal; the real fix (auto-detect, or drop it once the repo
 * moves off @wordpress/data 6.6.1) is tracked separately — see the migration PR.
 *
 * Careful: @wordpress/private-apis needs the exact opposite treatment, so don't
 * reach for the trick above when it misbehaves. It keeps a lock/unlock registry
 * that only works while every consumer shares one module instance — npm's
 * hoisting gives that for free, pnpm's isolation doesn't. What currently keeps
 * it to a single copy is the `@wordpress/dataviews>*` pin block in package.json,
 * which is why those pins are load-bearing rather than cosmetic: repinning them
 * (or pinning dataviews itself back to an older line) splits the instance and
 * Jest dies with `Cannot unlock an object that was not locked before`, from a
 * stack that points into node_modules and explains nothing. Giving private-apis
 * its own copy per package is the wrong direction — it is the failure, not the
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
