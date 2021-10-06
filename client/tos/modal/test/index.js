/**
 * External dependencies
 */
import {
	render,
	fireEvent,
	waitForElementToBeRemoved,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import TosModal from '../';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn( () => Promise.resolve() ),
} ) );

/**
 * Returns HTML element containing modal markup.
 *
 * Modal component is rendered inside React portal rather than inside container returned by testing-library,
 * hence cannot be accessed directly for snapshot matching.
 * https://github.com/WordPress/gutenberg/blob/master/packages/components/src/modal/index.js
 *
 * @return {HTMLElement} Modal element.
 */
const getModalContainer = () => {
	const [ modalContainer ] = document.body.getElementsByClassName(
		'woocommerce-payments__tos-modal'
	);

	return modalContainer;
};

const TOS_MODAL_HEADING = 'WooCommerce Payments: Terms of Service';
const DISABLE_PLUGIN_MODAL_HEADING = 'Disable WooCommerce Payments';

const renderModal = () => {
	const { getByRole, ...rest } = render( <TosModal /> );
	return {
		...rest,
		getByRole,
		getHeading: ( heading ) => getByRole( 'heading', { name: heading } ),
		clickActionButton: ( name ) =>
			fireEvent.click( getByRole( 'button', { name } ) ),
	};
};

describe( 'ToS modal', () => {
	beforeAll( () => {
		global.wcpay_tos_settings = {
			trackStripeConnected: {
				is_existing_stripe_account: true,
			},
		};
	} );

	afterAll( () => {
		delete global.wcpay_tos_settings;
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should render ToS modal', () => {
		const { getHeading } = renderModal();
		expect( getHeading( TOS_MODAL_HEADING ) ).toBeInTheDocument();
		expect( getModalContainer() ).toMatchSnapshot();
	} );

	test( 'should accept ToS', async () => {
		const { clickActionButton, queryByText } = renderModal();
		clickActionButton( 'Accept' );

		expect( apiFetch ).toBeCalledTimes( 1 );
		expect( apiFetch ).toBeCalledWith( {
			path: '/wc/v3/payments/tos',
			method: 'POST',
			data: { accept: true },
		} );

		await waitForElementToBeRemoved( () =>
			queryByText( TOS_MODAL_HEADING )
		);

		expect(
			queryByText( DISABLE_PLUGIN_MODAL_HEADING )
		).not.toBeInTheDocument();
	} );

	test( 'should render disable plugin modal on decline', () => {
		const { clickActionButton, getHeading, queryByText } = renderModal();
		clickActionButton( 'Decline' );

		expect(
			getHeading( DISABLE_PLUGIN_MODAL_HEADING )
		).toBeInTheDocument();
		expect( queryByText( TOS_MODAL_HEADING ) ).not.toBeInTheDocument();
		expect( getModalContainer() ).toMatchSnapshot();
	} );

	test( 'should return to ToS modal on back', () => {
		const { clickActionButton, getHeading, queryByText } = renderModal();

		clickActionButton( 'Decline' );

		expect(
			getHeading( DISABLE_PLUGIN_MODAL_HEADING )
		).toBeInTheDocument();
		expect( queryByText( TOS_MODAL_HEADING ) ).not.toBeInTheDocument();

		clickActionButton( 'Back' );

		expect( getHeading( TOS_MODAL_HEADING ) ).toBeInTheDocument();
		expect(
			queryByText( DISABLE_PLUGIN_MODAL_HEADING )
		).not.toBeInTheDocument();
	} );

	test( 'should decline ToS on disable', async () => {
		const { clickActionButton, queryByText } = renderModal();
		clickActionButton( 'Decline' );
		clickActionButton( 'Disable' );

		expect( apiFetch ).toBeCalledTimes( 1 );
		expect( apiFetch ).toBeCalledWith( {
			path: '/wc/v3/payments/tos',
			method: 'POST',
			data: { accept: false },
		} );

		await waitForElementToBeRemoved( () =>
			queryByText( DISABLE_PLUGIN_MODAL_HEADING )
		);

		expect( queryByText( TOS_MODAL_HEADING ) ).not.toBeInTheDocument();
	} );
} );
