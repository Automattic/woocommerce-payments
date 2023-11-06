/**
 * Internal dependencies
 */
import { getAppearance } from 'wcpay/checkout/upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { useFingerprint } from '../hooks';
import { LoadableBlock } from 'wcpay/components/loadable';
import { Elements } from '@stripe/react-stripe-js';
import { useEffect, useState } from 'react';
import PaymentProcessor from './payment-processor';
import { getPaymentMethodTypes } from 'wcpay/checkout/utils/upe';

const PaymentElements = ( {
	api,
	paymentMethodId,
	upeMethods,
	testingInstructions,
	...props
} ) => {
	const stripe = api.getStripeForUPE( paymentMethodId );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const currency = getUPEConfig( 'currency' ).toLowerCase();
	const paymentMethodTypes = getPaymentMethodTypes( paymentMethodId );

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			let upeAppearance = getAppearance( true );
			upeAppearance = await api.saveUPEAppearance(
				upeAppearance,
				'true'
			);
			setAppearance( upeAppearance );
		}

		if ( ! appearance ) {
			generateUPEAppearance();
		}
	}, [ api, appearance ] );

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
				} }
			>
				<PaymentProcessor
					api={ api }
					errorMessage={ fingerprintErrorMessage }
					fingerprint={ fingerprint }
					paymentMethodId={ paymentMethodId }
					upeMethods={ upeMethods }
					testingInstructions={ testingInstructions }
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
