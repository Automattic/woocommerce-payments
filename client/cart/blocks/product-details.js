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
import { useEffect, useRef, useState, useCallback } from 'react';

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
	const [ loaderHeight, setLoaderHeight ] = useState( null );
	const [ loaderMargin, setLoaderMargin ] = useState( null );
	const loaderHeightRef = useRef( loaderHeight );
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
		loaderHeightRef.current = loaderHeight;
	}, [ loaderHeight ] );

	useEffect( () => {
		// Show loader when cart total is updated and it is greater than 0.
		if (
			cart.cartTotals.total_price > 0 &&
			loaderHeightRef.current !== null
		) {
			setShowLoader( true );
			const timer = setTimeout( () => {
				setShowLoader( false );
			}, 1000 );
			return () => clearTimeout( timer );
		}
	}, [ cart.cartTotals.total_price, loaderHeightRef ] );

	const updateLoaderHeight = useCallback( () => {
		// Wait 500ms before getting the height of the element to account for the animation
		setTimeout( () => {
			const pmmeContainer = wrapperRef.current.querySelector(
				'.__PrivateStripeElement'
			);
			if ( pmmeContainer ) {
				setLoaderHeight( pmmeContainer.offsetHeight );
				setLoaderMargin( pmmeContainer.style.margin );
			}
		}, 500 );
	}, [ wrapperRef ] );

	useEffect( () => {
		window.addEventListener( 'resize', updateLoaderHeight );
		return () => {
			window.removeEventListener( 'resize', updateLoaderHeight );
		};
	}, [ updateLoaderHeight ] );

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

	return (
		<div className="wc-block-components-bnpl-wrapper">
			{ showLoader ? (
				<div
					className="pmme-loading"
					style={ {
						height: `${ loaderHeight }px`,
						margin: loaderMargin,
					} }
				></div>
			) : null }
			<div
				style={ { display: showLoader ? 'none' : 'block' } }
				ref={ wrapperRef }
			>
				<Elements
					stripe={ stripe }
					options={ { appearance, fonts: fontRules } }
				>
					<PaymentMethodMessagingElement
						options={ options }
						onReady={ updateLoaderHeight }
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
