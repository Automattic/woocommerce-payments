/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentActivity from '..';

describe( 'PaymentActivity component', () => {
	it( 'should render', () => {
		const { container } = render( <PaymentActivity /> );

		expect( container ).toMatchSnapshot();
	} );
} );
