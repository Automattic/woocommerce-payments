/**
 * External dependencies
 */
import { useEffect, useState, RawHTML, useRef } from '@wordpress/element';
import { Elements } from '@stripe/react-stripe-js';
// eslint-disable-next-line import/no-unresolved
import { StoreNotice } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import './style.scss';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import {
	getCachedAppearance,
	setCachedAppearance,
	dispatchAppearanceEvent,
} from 'wcpay/utils/appearance-cache';
import { useStripeForUPE } from 'wcpay/hooks/use-stripe-async';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { useFingerprint } from './hooks';
import { LoadableBlock } from 'wcpay/components/loadable';
import PaymentProcessor from './payment-processor';
import { getPaymentMethodTypes } from 'wcpay/checkout/utils/upe';

const PaymentElements = ( { api, ...props } ) => {
	const stripeForUPE = useStripeForUPE( api, props.paymentMethodId );
	const containerRef = useRef( null );

	const [ errorMessage, setErrorMessage ] = useState( null );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( undefined );
	const [ appearance, setAppearance ] = useState( () =>
		getCachedAppearance(
			'blocks_checkout',
			getUPEConfig( 'stylesCacheVersion' )
		)
	);
	const [ fontRules, setFontRules ] = useState( [] );

	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const currency = getUPEConfig( 'currency' ).toLowerCase();
	const paymentMethodTypes = getPaymentMethodTypes( props.paymentMethodId );

	useEffect( () => {
		if ( ! appearance && containerRef.current ) {
			setFontRules(
				getFontRulesFromPage( containerRef.current.ownerDocument )
			);
			// Generate UPE input styles.
			const upeAppearance = getAppearance(
				'blocks_checkout',
				false,
				containerRef.current.ownerDocument
			);
			dispatchAppearanceEvent( upeAppearance, 'blocks_checkout' );
			setCachedAppearance(
				'blocks_checkout',
				getUPEConfig( 'stylesCacheVersion' ),
				upeAppearance
			);
			setAppearance( upeAppearance );
			// Defer dispatch so all payment method label listeners are attached first.
			setTimeout( () => {
				window.dispatchEvent( new Event( 'wcpay-appearance-cached' ) );
			}, 0 );
		}

		if ( fingerprintErrorMessage ) {
			setErrorMessage( fingerprintErrorMessage );
		}
	}, [
		appearance,
		fingerprint,
		fingerprintErrorMessage,
		props.paymentMethodId,
	] );

	return (
		<>
			<LoadableBlock
				isLoading={ ! appearance || ! stripeForUPE }
				numLines={ 3 }
			>
				<Elements
					stripe={ stripeForUPE }
					options={ {
						mode: amount < 1 ? 'setup' : 'payment',
						amount: amount,
						currency: currency,
						paymentMethodCreation: 'manual',
						paymentMethodTypes: paymentMethodTypes,
						appearance: appearance,
						fonts: fontRules,
					} }
				>
					{ paymentProcessorLoadErrorMessage?.error?.message && (
						<div className="wc-block-components-notices">
							<StoreNotice status="error" isDismissible={ false }>
								<RawHTML>
									{
										paymentProcessorLoadErrorMessage.error
											.message
									}
								</RawHTML>
							</StoreNotice>
						</div>
					) }
					<PaymentProcessor
						api={ api }
						errorMessage={ errorMessage }
						fingerprint={ fingerprint }
						onLoadError={ setPaymentProcessorLoadErrorMessage }
						theme={ appearance?.theme }
						{ ...props }
					/>
				</Elements>
			</LoadableBlock>
			<div ref={ containerRef } />
		</>
	);
};

export const getDeferredIntentCreationUPEFields = (
	upeName,
	upeMethods,
	api,
	testingInstructions
) => {
	return (
		<PaymentElements
			paymentMethodId={ upeName }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
		/>
	);
};
