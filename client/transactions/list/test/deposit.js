/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Deposit from '../deposit';

describe( 'Deposit', () => {
	test( 'renders with date and deposit available', () => {
		const { container: link } = render(
			<Deposit dateAvailable="2020-01-07 00:00:00" depositId="po_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with date available but no deposit', () => {
		const { container: link } = render(
			<Deposit dateAvailable="2020-01-07 00:00:00" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with deposit but no date available', () => {
		const { container: link } = render(
			<Deposit depositId="po_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with no date or deposit available', () => {
		const { container: link } = render(
			<Deposit />
		);
		expect( link ).toMatchSnapshot();
	} );
} );
