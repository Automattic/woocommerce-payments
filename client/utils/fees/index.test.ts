/**
 * Internal dependencies
 */
import { formatFee } from 'utils/fees';

describe( 'Fees utilities', () => {
	it.each( [
		[ 0.03, 3 ],
		[ 0.0175, 1.75 ],
		[ 0.005, 0.5 ],
		[ 0.0005, 0.05 ],
		[ 0.00011, 0.011 ],
		[ 0.00019, 0.019 ],
		[ 0.000011, 0.001 ],
		[ 0.000019, 0.002 ],
	] )(
		'returns an input fee (%f) as a formatted fee (%f)',
		( inputFee, expectedFee ) => {
			expect( formatFee( inputFee ) ).toBe( expectedFee );
		}
	);
} );
