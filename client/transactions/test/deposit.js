/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Deposit from '../deposit';

describe( 'Deposit', () => {
	test( 'renders with date and deposit available', () => {
		const link = shallow(
			<Deposit dateAvailable="2020-01-07 00:00:00" depositId="po_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with date available but no deposit', () => {
		const link = shallow(
			<Deposit dateAvailable="2020-01-07 00:00:00" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with deposit but no date available', () => {
		const link = shallow(
			<Deposit depositId="po_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with no date or deposit available', () => {
		const link = shallow(
			<Deposit />
		);
		expect( link ).toMatchSnapshot();
	} );
} );
