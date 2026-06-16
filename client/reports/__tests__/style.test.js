/** @format */

const fs = require( 'fs' );
const path = require( 'path' );

describe( 'Reports styles', () => {
	it( 'ships Balance loading skeleton fallback styles with the Reports entry stylesheet', () => {
		const styles = fs.readFileSync(
			path.resolve( process.cwd(), 'client/reports/style.scss' ),
			'utf8'
		);

		expect( styles ).toContain( '.wcpay-reports-balance__skeleton' );
		expect( styles ).toContain( 'filter: blur( 4px );' );
		expect( styles ).toContain( '&-status' );
		expect( styles ).toContain(
			'@keyframes wcpay-reports-balance-skeleton-shimmer'
		);
	} );
} );
