/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountStatus from '../';

describe( 'AccountStatus', () => {
	const renderAccountStatus = ( accountStatus ) => {
		return render( <AccountStatus accountStatus={ accountStatus } /> );
	};

	test( 'renders connected account', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'complete',
			paymentsEnabled: true,
			deposits: {
				status: 'enabled',
				interval: 'daily',
			},
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted soon account', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'restricted_soon',
			paymentsEnabled: true,
			deposits: {
				status: 'enabled',
				interval: 'daily',
			},
			currentDeadline: 1583844589,
			accountLink:
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&wcpay-login=1',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted account with overdue requirements', () => {
		const accountStatus = renderAccountStatus( {
			status: 'restricted',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: '',
			},
			currentDeadline: 1583844589,
			pastDue: true,
			accountLink:
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&wcpay-login=1',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'restricted',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: 'daily',
			},
			currentDeadline: 1583844589,
			pastDue: false,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders partially restricted account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'partially_restricted',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: 'daily',
			},
			currentDeadline: 1583844589,
			pastDue: false,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.other account', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'rejected.other',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: 'monthly',
			},
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.fraud account', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'rejected.fraud',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: 'daily',
			},
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.terms_of_service account', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'rejected.terms_of_service',
			paymentsEnabled: false,
			deposits: {
				status: 'disabled',
				interval: 'daily',
			},
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders manual deposits', () => {
		const { container: accountStatus } = renderAccountStatus( {
			status: 'complete',
			paymentsEnabled: true,
			deposits: {
				status: 'enabled',
				interval: 'manual',
			},
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );
} );
