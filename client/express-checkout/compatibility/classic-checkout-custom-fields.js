/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from '../utils';

const EXTENSION_NAMESPACE = 'woocommerce-payments/express-checkout';
const CUSTOM_CHECKOUT_DATA_KEY = 'custom_checkout_data';

const normalizeFormDataValue = ( value ) => {
	if ( typeof File !== 'undefined' && value instanceof File ) {
		return value.name;
	}

	return value;
};

const getNamedFormElement = ( form, fieldName ) =>
	form.elements.namedItem( fieldName ) ||
	form.elements.namedItem( `${ fieldName }[]` );

const getCustomCheckoutFieldValue = ( form, formData, fieldName ) => {
	const values = formData.has( fieldName )
		? formData.getAll( fieldName )
		: formData.getAll( `${ fieldName }[]` );

	if ( values.length > 1 ) {
		return values.map( normalizeFormDataValue );
	}

	if ( values.length === 1 ) {
		return normalizeFormDataValue( values[ 0 ] );
	}

	return getNamedFormElement( form, fieldName ) ? '' : undefined;
};

const getCustomCheckoutData = () => {
	if ( getExpressCheckoutData( 'button_context' ) !== 'checkout' ) {
		return {};
	}

	const customCheckoutFields =
		getExpressCheckoutData( 'custom_checkout_fields' ) ?? {};
	const fieldNames = Object.keys( customCheckoutFields );

	if ( fieldNames.length === 0 ) {
		return {};
	}

	const checkoutForm = document.querySelector( 'form[name="checkout"]' );
	if ( ! checkoutForm || typeof FormData === 'undefined' ) {
		return {};
	}

	const formData = new FormData( checkoutForm );
	return fieldNames.reduce( ( customCheckoutData, fieldName ) => {
		const fieldValue = getCustomCheckoutFieldValue(
			checkoutForm,
			formData,
			fieldName
		);

		if ( fieldValue !== undefined ) {
			customCheckoutData[ fieldName ] = fieldValue;
		}

		return customCheckoutData;
	}, {} );
};

addFilter(
	'wcpay.express-checkout.cart-place-order-extension-data',
	'automattic/wcpay/express-checkout-custom-fields',
	( extensionData ) => {
		const customCheckoutData = getCustomCheckoutData();

		if ( Object.keys( customCheckoutData ).length === 0 ) {
			return extensionData;
		}

		return {
			...extensionData,
			[ EXTENSION_NAMESPACE ]: {
				...( extensionData[ EXTENSION_NAMESPACE ] ?? {} ),
				[ CUSTOM_CHECKOUT_DATA_KEY ]:
					JSON.stringify( customCheckoutData ),
			},
		};
	}
);
