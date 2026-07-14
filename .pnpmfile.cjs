/**
 * pnpm hook to fix peer dependency resolution issues.
 *
 * Several @woocommerce/* packages declare `@wordpress/data` as a peer. With
 * pnpm, a peer resolves to whatever the consumer provides — here the repo pins
 * @wordpress/data 6.6.1, which does NOT satisfy the ^10.x range these packages
 * are built against. Two problems follow in Jest:
 *   - @woocommerce/data's build calls `createReduxStore`, which the 6.6.1 module
 *     it gets handed doesn't expose the way its 10.x-compiled code expects.
 *   - `jest.mock('@wordpress/data')` replaces the shared module for every
 *     importer, including packages that need the real implementation.
 *
 * Converting the peer to a regular dependency makes pnpm install each package
 * its own @wordpress/data copy (at the version its peer range asks for), which
 * matches npm's nested resolution and keeps those copies out of the mock.
 */
const PACKAGES_NEEDING_OWN_WP_DATA = [
	'@woocommerce/components',
	'@woocommerce/data',
];

function readPackage( pkg ) {
	if (
		PACKAGES_NEEDING_OWN_WP_DATA.includes( pkg.name ) &&
		pkg.peerDependencies &&
		pkg.peerDependencies[ '@wordpress/data' ]
	) {
		pkg.dependencies = pkg.dependencies || {};
		pkg.dependencies[ '@wordpress/data' ] =
			pkg.peerDependencies[ '@wordpress/data' ];
		delete pkg.peerDependencies[ '@wordpress/data' ];
	}
	return pkg;
}

module.exports = {
	hooks: {
		readPackage,
	},
};
