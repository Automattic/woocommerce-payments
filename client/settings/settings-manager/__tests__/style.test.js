/** @format */

const fs = require( 'fs' );
const path = require( 'path' );

describe( 'Settings manager styles', () => {
	const readSettingsManagerStyles = () =>
		fs.readFileSync(
			path.resolve(
				process.cwd(),
				'client/settings/settings-manager/style.scss'
			),
			'utf8'
		);

	// WooCommerce leaves gateway settings tabs transparent, so the WooPayments
	// white content background would otherwise show through the tab strip.
	it( 'keeps the settings tabs on the WordPress admin gray background', () => {
		const styles = readSettingsManagerStyles();

		expect( styles ).toMatch(
			/\.nav-tab-wrapper\s*{\s*background:\s*\$wp-gray-2;/
		);
	} );
} );
