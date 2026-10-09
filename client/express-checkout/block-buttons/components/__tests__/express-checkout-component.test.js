/**
 * External dependencies
 */
import { act, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from '../express-checkout-component';

let mockElementProps;

jest.mock( '@stripe/react-stripe-js', () => ( {
	ExpressCheckoutElement: ( props ) => {
		mockElementProps = props;
		return <div data-testid="express-checkout-element" />;
	},
} ) );

jest.mock( '../../hooks/use-express-checkout', () => ( {
	useExpressCheckout: () => ( {
		buttonOptions: { buttonHeight: 48, buttonTheme: {} },
		onButtonClick: jest.fn(),
		onConfirm: jest.fn(),
		onReady: jest.fn(),
		onCancel: jest.fn(),
		elements: {},
	} ),
} ) );

const renderInBlockSlot = () => {
	const list = document.createElement( 'ul' );
	const slot = document.createElement( 'li' );
	slot.id =
		'express-payment-method-woocommerce_payments_express_checkout_applePay';
	list.appendChild( slot );
	document.body.appendChild( list );

	const { getByTestId } = render(
		<ExpressCheckoutComponent
			billing={ {} }
			shippingData={ {} }
			expressPaymentMethod="applePay"
		/>,
		{ container: slot }
	);

	return {
		slot,
		list,
		wrapper: getByTestId( 'express-checkout-element' ).parentElement,
	};
};

describe( 'ExpressCheckoutComponent', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'hides itself without removing the block slot when the wallet is unavailable', () => {
		const { slot, list, wrapper } = renderInBlockSlot();

		act( () => {
			mockElementProps.onReady( {
				availablePaymentMethods: { applePay: false },
			} );
		} );

		expect( wrapper.hidden ).toBe( true );
		expect( list.contains( slot ) ).toBe( true );
	} );

	it( 'shows itself again when the wallet becomes available', () => {
		const { wrapper } = renderInBlockSlot();

		act( () => {
			mockElementProps.onReady( {
				availablePaymentMethods: { applePay: false },
			} );
		} );
		act( () => {
			mockElementProps.onReady( {
				availablePaymentMethods: { applePay: true },
			} );
		} );

		expect( wrapper.hidden ).toBe( false );
	} );
} );
