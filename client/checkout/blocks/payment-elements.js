/**
 * External dependencies
 */
import { useEffect, useState, RawHTML } from '@wordpress/element';
import { Elements } from '@stripe/react-stripe-js';
// eslint-disable-next-line import/no-unresolved
import { StoreNotice } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import './style.scss';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { useFingerprint } from './hooks';
import { LoadableBlock } from 'wcpay/components/loadable';
import PaymentProcessor from './payment-processor';
import { getPaymentMethodTypes } from 'wcpay/checkout/utils/upe';

const PaymentElements = ( { api, ...props } ) => {
	const stripe = api.getStripeForUPE( props.paymentMethodId );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( undefined );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fontRules ] = useState( getFontRulesFromPage() );
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const currency = getUPEConfig( 'currency' ).toLowerCase();
	const paymentMethodTypes = getPaymentMethodTypes( props.paymentMethodId );

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			let upeAppearance = getAppearance( 'blocks_checkout', false, true );
			upeAppearance = await api.saveUPEAppearance(
				upeAppearance,
				'blocks_checkout'
			);
			setAppearance( upeAppearance );
		}

		if ( ! appearance ) {
			generateUPEAppearance();
		}

		if ( fingerprintErrorMessage ) {
			setErrorMessage( fingerprintErrorMessage );
		}
	}, [
		api,
		appearance,
		fingerprint,
		fingerprintErrorMessage,
		props.paymentMethodId,
	] );

	return (
		<LoadableBlock isLoading={ ! appearance } numLines={ 3 }>
			<Elements
				stripe={ stripe }
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
