/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentOverviewDataHighlight from '../';

describe( 'PaymentOverviewDataHighlight', () => {
	test( 'renders component', () => {
		const { container: container } = render(
			<PaymentOverviewDataHighlight />
		);

		expect( container ).toMatchSnapshot();
	} );
} );
