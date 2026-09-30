/** @format */

const fs = require( 'fs' );
const path = require( 'path' );

describe( 'Reports styles', () => {
	const readReportsStyles = () =>
		fs.readFileSync(
			path.resolve( process.cwd(), 'client/reports/style.scss' ),
			'utf8'
		);

	// WooCommerce core paints `.woocommerce-layout__header` #f0f0f1; the Reports
	// stylesheet overrides it to #fff. This only matches the SCSS source — it's a
	// tripwire against the override being silently dropped in a refactor, not a
	// check of the rendered cascade.
	it( 'ships a `.woocommerce-layout__header` background override in the Reports stylesheet', () => {
		const styles = readReportsStyles();

		expect( styles ).toMatch(
			/\.woocommerce-layout__header\s*{\s*background:\s*#fff;/
		);
	} );

	it( 'ships Balance loading skeleton fallback styles with the Reports entry stylesheet', () => {
		const styles = readReportsStyles();

		expect( styles ).toContain( '.wcpay-reports-balance__skeleton' );
		expect( styles ).toContain( 'filter: blur( 4px );' );
		expect( styles ).toContain( '&-status' );
		expect( styles ).toContain(
			'@keyframes wcpay-reports-balance-skeleton-shimmer'
		);
	} );
} );
