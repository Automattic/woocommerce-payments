/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Pill from '..';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( { className, children } ) => (
			<span className={ className }>{ children }</span>
		),
	};
} );

describe( 'Pill', () => {
	test( 'Pill without a type', () => {
		const { container } = render( <Pill>Test</Pill> );
		expect( container ).toMatchSnapshot(); // classname wcpay-pill__primary
	} );

	test( 'Pill with type "primary"', () => {
		const { container } = render( <Pill type="primary">Primary</Pill> );
		expect( container ).toMatchSnapshot(); // classname wcpay-pill__primary
	} );

	test( 'Pill with type "success"', () => {
		const { container } = render( <Pill type="success">Success</Pill> );
		expect( container ).toMatchSnapshot(); // classname wcpay-pill__success
	} );
} );
