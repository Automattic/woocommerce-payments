/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RedirectToTransactionDetails from '..';
import { useDispute } from 'wcpay/data/disputes';
import { getAdminUrl } from 'wcpay/utils';
import type { Dispute } from 'wcpay/types/disputes';

jest.mock( 'wcpay/data/disputes', () => ( {
	useDispute: jest.fn(),
} ) );

const mockHistoryReplace = jest.fn();
jest.mock( '@woocommerce/navigation', () => ( {
	getPersistedQuery: () => ( {} ),
	getHistory: () => ( {
		replace: mockHistoryReplace,
	} ),
} ) );

const mockCreateInfoNotice = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	createReduxStore: jest.fn( ( name ) => name ),
	register: jest.fn(),
	combineReducers: jest.fn(),
	select: jest.fn(),
	dispatch: jest.fn(),
	useSelect: jest.fn(),
	useDispatch: jest.fn( ( storeName ) =>
		storeName === 'core/notices'
			? { createInfoNotice: mockCreateInfoNotice }
			: {}
	),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
	createRegistryControl: jest.fn(),
	registerStore: jest.fn(),
} ) );

const mockUseDispute = useDispute as jest.MockedFunction< typeof useDispute >;

const transactionDetailsUrl = getAdminUrl( {
	page: 'wc-admin',
	path: '/payments/transactions/details',
	id: 'pi_1',
	transaction_id: 'txn_1',
	type: 'dispute',
} );
const disputesListUrl = getAdminUrl( {
	page: 'wc-admin',
	path: '/payments/disputes',
} );

describe( 'RedirectToTransactionDetails', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	const renderRedirect = () =>
		render( <RedirectToTransactionDetails query={ { id: 'dp_1' } } /> );

	it( 'redirects to the transaction details screen when the dispute resolves with a charge', () => {
		mockUseDispute.mockReturnValue( {
			dispute: {
				id: 'dp_1',
				payment_intent: 'pi_1',
				charge: { balance_transaction: 'txn_1' },
			} as unknown as Dispute,
			isLoading: false,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).toHaveBeenCalledWith(
			transactionDetailsUrl
		);
		expect( mockCreateInfoNotice ).not.toHaveBeenCalled();
	} );

	it( 'falls back to the disputes list (with a notice) when the dispute cannot be retrieved', () => {
		mockUseDispute.mockReturnValue( {
			dispute: undefined,
			isLoading: false,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).toHaveBeenCalledWith( disputesListUrl );
		expect( mockCreateInfoNotice ).toHaveBeenCalledWith(
			expect.stringContaining( "couldn't open that dispute" ),
			{ type: 'snackbar' }
		);
	} );

	it( 'falls back to the disputes list when the balance transaction is missing', () => {
		mockUseDispute.mockReturnValue( {
			dispute: {
				id: 'dp_1',
				payment_intent: 'pi_1',
				charge: {},
			} as unknown as Dispute,
			isLoading: false,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).toHaveBeenCalledWith( disputesListUrl );
	} );

	it( 'falls back to the disputes list when the payment intent is missing', () => {
		mockUseDispute.mockReturnValue( {
			dispute: {
				id: 'dp_1',
				charge: { balance_transaction: 'txn_1' },
			} as unknown as Dispute,
			isLoading: false,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).toHaveBeenCalledWith( disputesListUrl );
	} );

	it( 'falls back to the disputes list when the balance transaction is an expanded object', () => {
		mockUseDispute.mockReturnValue( {
			dispute: {
				id: 'dp_1',
				payment_intent: 'pi_1',
				charge: { balance_transaction: { id: 'txn_1' } },
			} as unknown as Dispute,
			isLoading: false,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).toHaveBeenCalledWith( disputesListUrl );
	} );

	it( 'does not redirect while the dispute is still loading', () => {
		mockUseDispute.mockReturnValue( {
			dispute: undefined,
			isLoading: true,
		} );

		renderRedirect();

		expect( mockHistoryReplace ).not.toHaveBeenCalled();
	} );
} );
