/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutComponent from '../express-checkout-component';

let mockElementProps;

jest.mock( '@stripe/react-stripe-js', () => ( {
	ExpressCheckoutElement: ( props ) => {
		mockElementProps = props;
		return <div />;
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

const renderInBlockSlot = ( onAvailabilityChange ) => {
	const list = document.createElement( 'ul' );
	const slot = document.createElement( 'li' );
	slot.id =
		'express-payment-method-woocommerce_payments_express_checkout_applePay';
	list.appendChild( slot );
	document.body.appendChild( list );

	render(
		<ExpressCheckoutComponent
			billing={ {} }
			shippingData={ {} }
			expressPaymentMethod="applePay"
			onAvailabilityChange={ onAvailabilityChange }
		/>,
		{ container: slot }
	);

	return { slot, list };
};

describe( 'ExpressCheckoutComponent', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'reports an unavailable wallet without removing the block slot', () => {
		const onAvailabilityChange = jest.fn();
		const { slot, list } = renderInBlockSlot( onAvailabilityChange );

		mockElementProps.onReady( {
			availablePaymentMethods: { applePay: false },
		} );

		expect( onAvailabilityChange ).toHaveBeenLastCalledWith( false );
		expect( list.contains( slot ) ).toBe( true );
	} );

	it( 'reports an available wallet', () => {
		const onAvailabilityChange = jest.fn();
		renderInBlockSlot( onAvailabilityChange );

		mockElementProps.onReady( {
			availablePaymentMethods: { applePay: true },
		} );

		expect( onAvailabilityChange ).toHaveBeenLastCalledWith( true );
	} );
} );
