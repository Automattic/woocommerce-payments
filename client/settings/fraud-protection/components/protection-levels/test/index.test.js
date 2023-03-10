/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProtectionLevels from '../index';

describe( 'ProtectionLevels', () => {
	it( 'renders', () => {
		const { container: protectionLevels } = render( <ProtectionLevels /> );

		expect( protectionLevels ).toMatchSnapshot();
	} );
} );
