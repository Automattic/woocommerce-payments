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
const STANDARD_CHECKOUT_FIELDS = new Set( [
	'billing_first_name',
	'billing_last_name',
	'billing_company',
	'billing_country',
	'billing_address_1',
	'billing_address_2',
	'billing_city',
	'billing_state',
	'billing_postcode',
	'billing_phone',
	'billing_email',
	'shipping_first_name',
	'shipping_last_name',
	'shipping_company',
	'shipping_country',
	'shipping_address_1',
	'shipping_address_2',
	'shipping_city',
	'shipping_state',
	'shipping_postcode',
	'shipping_phone',
	'order_comments',
] );
const INTERNAL_CHECKOUT_FIELDS = new Set( [
	'payment_method',
	'terms',
	'terms-field',
	'privacy_policy',
	'ship_to_different_address',
	'createaccount',
	'account_username',
	'account_password',
	'account_password-2',
	'woocommerce-process-checkout-nonce',
] );
const INTERNAL_CHECKOUT_FIELD_PREFIXES = [
	'_',
	'wc-',
	'wc_order_attribution_',
	'woocommerce_',
	'wcpay_',
];
const IGNORED_FORM_FIELD_TYPES = new Set( [ 'button', 'reset', 'submit' ] );

const normalizeFieldName = ( fieldName ) => fieldName.replace( /\[\]$/, '' );

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
	const namedElement = getNamedFormElement( form, fieldName );

	if ( values.length > 1 && namedElement?.type === 'select-one' ) {
		return normalizeFormDataValue( namedElement.value );
	}

	if ( values.length > 1 ) {
		return values.map( normalizeFormDataValue );
	}

	if ( values.length === 1 ) {
		return normalizeFormDataValue( values[ 0 ] );
	}

	return namedElement ? '' : undefined;
};

const isSupportedCheckoutFormElement = ( element ) => {
	const tagName = element.tagName?.toLowerCase();

	if ( ! [ 'input', 'select', 'textarea' ].includes( tagName ) ) {
		return false;
	}

	if ( ! element.name || element.disabled ) {
		return false;
	}

	return ! IGNORED_FORM_FIELD_TYPES.has(
		( element.type ?? '' ).toLowerCase()
	);
};

const isInternalCheckoutFieldName = ( fieldName, registeredFieldNames ) => {
	if ( registeredFieldNames.has( fieldName ) ) {
		return false;
	}

	return (
		STANDARD_CHECKOUT_FIELDS.has( fieldName ) ||
		INTERNAL_CHECKOUT_FIELDS.has( fieldName ) ||
		INTERNAL_CHECKOUT_FIELD_PREFIXES.some( ( prefix ) =>
			fieldName.startsWith( prefix )
		)
	);
};

const getCheckoutFormFieldNames = ( form, registeredFieldNames ) => {
	return Array.from( form.elements )
		.filter( isSupportedCheckoutFormElement )
		.map( ( element ) => normalizeFieldName( element.name ) )
		.filter(
			( fieldName ) =>
				fieldName &&
				! isInternalCheckoutFieldName( fieldName, registeredFieldNames )
		);
};

const getCustomCheckoutData = () => {
	if ( getExpressCheckoutData( 'button_context' ) !== 'checkout' ) {
		return {};
	}

	const checkoutForm = document.querySelector( 'form[name="checkout"]' );
	if ( ! checkoutForm || typeof FormData === 'undefined' ) {
		return {};
	}

	const customCheckoutFields =
		getExpressCheckoutData( 'custom_checkout_fields' ) ?? {};
	const registeredFieldNames = new Set( Object.keys( customCheckoutFields ) );
	// Registered fields are validated and saved server-side. Additional form
	// fields are exposed for extension hooks.
	const fieldNames = [
		...new Set( [
			...registeredFieldNames,
			...getCheckoutFormFieldNames( checkoutForm, registeredFieldNames ),
		] ),
	];

	if ( fieldNames.length === 0 ) {
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
