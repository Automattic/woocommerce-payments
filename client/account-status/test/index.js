/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';
/**
 * Internal dependencies
 */
import AccountStatus from '../';

describe( 'AccountStatus', () => {
	const renderAccountStatus = ( accountStatus ) => {
		return shallow( <AccountStatus accountStatus={ accountStatus } /> );
	};

	test( 'renders connected account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'complete',
			paymentsEnabled: true,
			depositsStatus: 'daily',
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted soon account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'restricted_soon',
			paymentsEnabled: true,
			depositsStatus: 'daily',
			currentDeadline: 1583844589,
			accountLink: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&wcpay-login=1',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted account with overdue requirements', () => {
		const accountStatus = renderAccountStatus( {
			status: 'restricted',
			paymentsEnabled: false,
			depositsStatus: 'disabled',
			currentDeadline: 1583844589,
			pastDue: true,
			accountLink: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&wcpay-login=1',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders restricted account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'restricted',
			paymentsEnabled: false,
			depositsStatus: 'disabled',
			currentDeadline: 1583844589,
			pastDue: false,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.other account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'rejected.other',
			paymentsEnabled: false,
			depositsStatus: 'disabled',
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.fraud account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'rejected.fraud',
			paymentsEnabled: false,
			depositsStatus: 'disabled',
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders rejected.terms_of_service account', () => {
		const accountStatus = renderAccountStatus( {
			status: 'rejected.terms_of_service',
			paymentsEnabled: false,
			depositsStatus: 'disabled',
			currentDeadline: 0,
			accountLink: '',
		} );
		expect( accountStatus ).toMatchSnapshot();
	} );
} );
