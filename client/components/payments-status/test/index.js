/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentsStatus from '../';

describe( 'PaymentsStatus', () => {
	test( 'renders enabled status', () => {
		const { container: paymentsStatus } = renderPaymentsStatus( 1, 20 );
		expect( paymentsStatus ).toMatchSnapshot();
	} );

	test( 'renders pending verification status', () => {
		const { container: paymentsStatus } = renderPaymentsStatus(
			0,
			20,
			'pending_verification'
		);
		expect( paymentsStatus ).toMatchSnapshot();
	} );

	test( 'renders disabled status', () => {
		const { container: paymentsStatus } = renderPaymentsStatus( 0, 20 );
		expect( paymentsStatus ).toMatchSnapshot();
	} );

	function renderPaymentsStatus( paymentsEnabled, iconSize, accountStatus ) {
		return render(
			<PaymentsStatus
				paymentsEnabled={ paymentsEnabled }
				accountStatus={ accountStatus }
				iconSize={ iconSize }
			/>
		);
	}
} );
