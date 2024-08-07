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
	const [ showLoader, setShowLoader ] = useState( true );
	const isPmmeReadyRef = useRef( false );

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
		if (
			cart.cartTotals.total_price > 0 &&
			isPmmeReadyRef.current === true
		) {
			setShowLoader( true );
			const timer = setTimeout( () => {
				setShowLoader( false );
			}, 1000 );
			return () => clearTimeout( timer );
		}
	}, [ cart.cartTotals.total_price, isPmmeReadyRef ] );

	const onReadyHandler = () => {
		isPmmeReadyRef.current = true;
		setShowLoader( false );
	};

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
			<div className="pmme-container">
				{ showLoader ? (
					<div className="pmme-loading">
						<div className="pmme-loading--line pmme-loading--header"></div>
						<div className="pmme-loading--line pmme-loading--body"></div>
					</div>
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
