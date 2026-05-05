/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import wooPayExpressCheckoutPaymentMethod from '../woopay-express-checkout-payment-method';
import usePreferredCard from '../use-preferred-card';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/api', () =>
	jest.fn().mockImplementation( () => ( {} ) )
);

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ) )
);

jest.mock( '../use-preferred-card', () => jest.fn().mockReturnValue( null ) );

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
		usePreferredCard.mockReturnValue( null );
	} );

	test( 'renders button without card when hook returns null', async () => {
		render( <Container /> );

		await waitFor( () => {
			const button = screen.getByTestId( 'woopay-button' );
			expect( button ).not.toHaveAttribute( 'data-preferred-card' );
		} );
	} );

	test( 'passes preferred card to button when hook returns card', async () => {
		const card = { brand: 'visa', last4: '4242' };
		usePreferredCard.mockReturnValue( card );

		render( <Container /> );

		await waitFor( () => {
			const button = screen.getByTestId( 'woopay-button' );
			expect( button ).toHaveAttribute(
				'data-preferred-card',
				JSON.stringify( card )
			);
		} );
	} );
} );
