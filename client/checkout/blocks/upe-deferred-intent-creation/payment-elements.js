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

const PaymentElements = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const currency = getUPEConfig( 'currency' ).toLowerCase();

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			const upeAppearance = getAppearance( true );
			await api.saveUPEAppearance( upeAppearance, 'true' );
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
					paymentMethodTypes: [ props.paymentMethodId ],
					appearance: appearance,
				} }
			>
				<PaymentProcessor
					api={ api }
					errorMessage={ errorMessage }
					fingerprint={ fingerprint }
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
