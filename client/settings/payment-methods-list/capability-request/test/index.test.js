/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import { useGetPaymentMethodStatuses } from 'wcpay/data';
import CapabilityNotice from '../capability-request-notice';

const CapabilityRequestListMock = {
	id: 'jcb',
	label: __( 'JCB', 'woocommerce-payments' ),
	country: 'JP',
	states: {
		unrequested: {
			status: 'info',
			content: __(
				'Enable JCB for your customers, the only international payment brand based in Japan.',
				'woocommerce-payments'
			),
			actions: 'request',
			actionsLabel: __( 'Enable JCB', 'woocommerce-payments' ),
		},
	},
};

jest.mock( 'wcpay/data', () => ( {
	useGetPaymentMethodStatuses: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	createReduxStore: jest.fn(),
	register: jest.fn(),
	registerStore: jest.fn(),
	combineReducers: jest.fn(),
	useSelect: jest.fn().mockReturnValue( {} ),
	select: jest.fn().mockReturnValue( {
		getSettings: jest.fn().mockReturnValue( {
			account_country: 'JP',
		} ),
	} ),
	useDispatch: jest.fn( () => ( {
		updateOptions: jest.fn(),
		createNotice: jest.fn(),
	} ) ),
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

global.wcpaySettings = {
	capabilityRequestNotices: [],
};

describe( 'CapabilityRequestNotice', () => {
	beforeEach( () => {
		useGetPaymentMethodStatuses.mockReturnValue( {
			jcb_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [],
			},
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should match the snapshot - Render UNREQUESTED CapabilityNotice', () => {
		const { container } = render(
			<CapabilityNotice
				id={ CapabilityRequestListMock.id }
				label={ CapabilityRequestListMock.label }
				country={ CapabilityRequestListMock.country }
				states={ CapabilityRequestListMock.states }
			/>
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render content and button - Render CapabilityNotice', () => {
		render(
			<CapabilityNotice
				id={ CapabilityRequestListMock.id }
				label={ CapabilityRequestListMock.label }
				country={ CapabilityRequestListMock.country }
				states={ CapabilityRequestListMock.states }
			/>
		);

		expect(
			screen.queryByText( /Enable JCB for your customers/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeInTheDocument();

		expect(
			screen.queryByRole( 'button', { name: 'Enable JCB' } )
		).toBeInTheDocument();
	} );

	it( 'should not render if country is not JP - CapabilityNotice', () => {
		render(
			<CapabilityNotice
				id={ CapabilityRequestListMock.id }
				label={ CapabilityRequestListMock.label }
				country={ 'US' }
				states={ CapabilityRequestListMock.states }
			/>
		);

		expect(
			screen.queryByRole( 'button', { name: 'Enable JCB' } )
		).not.toBeInTheDocument();
	} );

	it( 'should not render if state is unknown - CapabilityNotice', () => {
		render(
			<CapabilityNotice
				id={ CapabilityRequestListMock.id }
				label={ CapabilityRequestListMock.label }
				country={ 'US' }
				states={ {} }
			/>
		);

		expect(
			screen.queryByRole( 'button', { name: 'Enable JCB' } )
		).not.toBeInTheDocument();
	} );
} );
