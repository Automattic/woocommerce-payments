/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import wooPayExpressCheckoutPaymentMethod from '../woopay-express-checkout-payment-method';
import {
	getCachedPreferredCard,
	setCachedPreferredCard,
	fetchPreferredCard,
} from '../preferred-card-utils';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/api', () =>
	jest.fn().mockImplementation( () => ( {} ) )
);

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ) )
);

jest.mock( '../preferred-card-utils', () => ( {
	getCachedPreferredCard: jest.fn().mockReturnValue( null ),
	setCachedPreferredCard: jest.fn(),
	fetchPreferredCard: jest.fn().mockResolvedValue( null ),
} ) );

jest.mock( '../woopay-express-checkout-button', () => ( {
	__esModule: true,
	WoopayExpressCheckoutButton: ( props ) => (
		<div
			data-testid="woopay-button"
			data-preferred-card={
				props.preferredCard
					? JSON.stringify( props.preferredCard )
					: undefined
			}
		/>
	),
} ) );

describe( 'WooPayExpressCheckoutButtonContainer', () => {
	let Container;

	beforeAll( () => {
		Container = wooPayExpressCheckoutPaymentMethod().content.type;
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		getCachedPreferredCard.mockReturnValue( null );
		fetchPreferredCard.mockResolvedValue( null );
	} );

	test( 'fetches preferred card on mount', async () => {
		render( <Container /> );

		await waitFor( () => {
			expect( fetchPreferredCard ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	test( 'caches card data when fetch succeeds', async () => {
		const card = { brand: 'visa', last4: '4242' };
		fetchPreferredCard.mockResolvedValue( card );

		render( <Container /> );

		await waitFor( () => {
			expect( setCachedPreferredCard ).toHaveBeenCalledWith( card );
		} );
	} );

	test( 'passes fetched card to button', async () => {
		const card = { brand: 'visa', last4: '4242' };
		fetchPreferredCard.mockResolvedValue( card );

		render( <Container /> );

		await waitFor( () => {
			const button = screen.getByTestId( 'woopay-button' );
			expect( button ).toHaveAttribute(
				'data-preferred-card',
				JSON.stringify( card )
			);
		} );
	} );

	test( 'initializes with cached card data', async () => {
		const cached = { brand: 'mastercard', last4: '5555' };
		getCachedPreferredCard.mockReturnValue( cached );

		render( <Container /> );

		await waitFor( () => {
			const button = screen.getByTestId( 'woopay-button' );
			expect( button ).toHaveAttribute(
				'data-preferred-card',
				JSON.stringify( cached )
			);
		} );
	} );

	test( 'keeps cached state when fetch fails', async () => {
		fetchPreferredCard.mockRejectedValue( new Error( 'timeout' ) );

		render( <Container /> );

		await waitFor( () => {
			expect( fetchPreferredCard ).toHaveBeenCalledTimes( 1 );
		} );

		expect( setCachedPreferredCard ).not.toHaveBeenCalled();
	} );
} );
