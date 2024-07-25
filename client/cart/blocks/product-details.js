/**
 * External dependencies
 */
import {
	Elements,
	PaymentMethodMessagingElement,
} from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getUPEConfig } from 'utils/checkout';
import WCPayAPI from '../../checkout/api';
import request from '../../checkout/utils/request';
import { useEffect, useRef, useState } from 'react';

import './style.scss';

// Create an API object, which will be used throughout the checkout.
const api = new WCPayAPI(
	{
		publishableKey: getUPEConfig( 'publishableKey' ),
		accountId: getUPEConfig( 'accountId' ),
		forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
		locale: getUPEConfig( 'locale' ),
	},
	request
);

const isInEditor = () => {
	const editorStore = select( 'core/editor' );

	return !! editorStore;
};

// BNPL only supports 2 decimal places.
const normalizeAmount = ( amount, decimalPlaces = 2 ) => {
	return amount * Math.pow( 10, 2 - decimalPlaces );
};

const { ExperimentalOrderMeta } = window.wc.blocksCheckout;

const ProductDetail = ( { cart, context } ) => {
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'upeBnplCartBlockAppearance' ) || {}
	);
	const [ fontRules ] = useState( getFontRulesFromPage() );
	const [ wrapperHeight, setWrapperHeight ] = useState( null );
	const [ showLoader, setShowLoader ] = useState( false );
	const wrapperRef = useRef( null );

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			let upeAppearance = getAppearance( 'bnpl_cart_block' );
			upeAppearance = await api.saveUPEAppearance(
				upeAppearance,
				'bnpl_cart_block'
			);
			setAppearance( upeAppearance );
		}

		if ( Object.keys( appearance ).length === 0 ) {
			generateUPEAppearance();
		}
	}, [ appearance ] );

	useEffect( () => {
		// Show loader when cart total is updated and it is greater than 0.
		if ( cart.cartTotals.total_price > 0 ) {
			setShowLoader( true );
			const timer = setTimeout( () => {
				setShowLoader( false );
			}, 1000 );
			return () => clearTimeout( timer );
		}
	}, [ cart.cartTotals.total_price ] );

	if ( Object.keys( appearance ).length === 0 ) {
		return null;
	}

	if ( context !== 'woocommerce/cart' ) {
		return null;
	}

	const cartTotal = normalizeAmount(
		cart.cartTotals.total_price,
		wcSettings.currency.precision
	);

	const {
		country,
		paymentMethods,
		currencyCode,
	} = window.wcpayStripeSiteMessaging;

	const amount = parseInt( cartTotal, 10 ) || 0;

	const options = {
		amount: amount,
		currency: currencyCode || 'USD',
		paymentMethodTypes: paymentMethods || [],
		countryCode: country, // Customer's country or base country of the store.
	};

	const stripe = api.getStripe();

	const onReadyHandler = () => {
		if ( wrapperRef.current && wrapperHeight === null ) {
			setWrapperHeight( wrapperRef.current.offsetHeight ); // Update height on element ready TODO: Fix to get correct height
		}
	};

	return (
		<div className="wc-block-components-bnpl-wrapper" ref={ wrapperRef }>
			{ showLoader ? (
				<div
					className="pmme-loading"
					style={ { height: `${ wrapperHeight }px` } }
				></div>
			) : null }
			<div style={ { display: showLoader ? 'none' : 'block' } }>
				<Elements
					stripe={ stripe }
					options={ { appearance, fonts: fontRules } }
				>
					<PaymentMethodMessagingElement
						options={ options }
						onReady={ onReadyHandler }
					/>
				</Elements>
			</div>
		</div>
	);
};

export const renderBNPLCartMessaging = () => {
	if ( isInEditor() ) {
		return null;
	}
	return (
		<ExperimentalOrderMeta>
			<ProductDetail />
		</ExperimentalOrderMeta>
	);
};
