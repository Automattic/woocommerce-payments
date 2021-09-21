/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsStatus from '../';

describe( 'DepositsStatus', () => {
	test( 'renders disabled status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'disabled',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders daily status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'daily',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders weekly status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'weekly',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders monthly status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'monthly',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders manual status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'manual',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders unknown status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'foobar',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	function renderDepositsStatus( depositsStatus, iconSize ) {
		return render(
			<DepositsStatus
				depositsStatus={ depositsStatus }
				iconSize={ iconSize }
			/>
		);
	}
} );
