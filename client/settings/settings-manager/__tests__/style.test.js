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

	// WooCommerce leaves gateway settings tabs transparent and uses a stronger
	// separator than the refreshed settings UI.
	it( 'matches the refreshed WooCommerce settings tab colors', () => {
		const styles = readSettingsManagerStyles();

		expect( styles ).toMatch(
			/\.nav-tab-wrapper\s*{\s*background:\s*\$wp-gray-2;\s*border-bottom-color:\s*#f0f0f0;/
		);
	} );
} );
