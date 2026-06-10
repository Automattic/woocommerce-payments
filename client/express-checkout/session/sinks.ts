/**
 * Internal dependencies
 */
import {
	appendPaymentMethodIdToForm,
	appendConfirmationTokenToForm,
	appendExpressPaymentTypeToForm,
	appendFraudPreventionTokenInputToForm,
} from 'wcpay/checkout/classic/upe-utils';
import { appendFingerprintInputToForm } from 'wcpay/checkout/utils/fingerprint';
import type { ExpressPaymentResult } from './payment-result';

declare global {
	interface Window {
		wcpayFraudPreventionToken?: string;
	}
}

/**
 * Delivers an {@link ExpressPaymentResult} (or an authorization error) to
 * WooCommerce. Each environment hands the credential to WooCommerce differently;
 * this is the seam where that knowledge - and the exact field names the WCPay
 * server expects - lives.
 */
export interface ExpressPaymentResultSink {
	success( result: ExpressPaymentResult ): unknown;
	error( message: string ): unknown;
}

interface BlocksMetaSinkContext {
	gatewayId: string;
	// eslint-disable-next-line @typescript-eslint/naming-convention
	responseTypes: { SUCCESS: string; ERROR: string; FAIL: string };
}

interface BlocksPaymentSetupResponse {
	type: string;
	message?: string;
	meta?: { paymentMethodData: Record< string, string > };
}

/**
 * Blocks checkout: WooCommerce reads `paymentMethodData` from the value the
 * `onPaymentSetup` callback returns, then submits the order itself.
 */
export const createBlocksMetaSink = ( {
	gatewayId,
	responseTypes,
}: BlocksMetaSinkContext ): ExpressPaymentResultSink => ( {
	success( result: ExpressPaymentResult ): BlocksPaymentSetupResponse {
		const credentialKey =
			result.credentialType === 'confirmation_token'
				? 'wcpay-confirmation-token'
				: 'wcpay-payment-method';

		return {
			type: responseTypes.SUCCESS,
			meta: {
				paymentMethodData: {
					payment_method: gatewayId,
					[ credentialKey ]: result.credentialId,
					express_payment_type: result.expressPaymentType,
					'wcpay-express-payment-method-types': JSON.stringify(
						result.stripePaymentMethodTypes
					),
					'wcpay-fraud-prevention-token':
						window.wcpayFraudPreventionToken ?? '',
				},
			},
		};
	},
	error( message: string ): BlocksPaymentSetupResponse {
		return { type: responseTypes.ERROR, message };
	},
} );

interface ClassicFormSinkContext {
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	$form: any;
	fingerprint: string;
	/** Triggers the checkout form submission (the WC custom-place-order `api.submit`). */
	onSubmit: () => void;
	/** Renders the authorization error to the shopper (jQuery notice, scroll, ...). */
	onError: ( message: string ) => void;
}

/**
 * Classic (shortcode) checkout: append the credential as hidden inputs on the
 * checkout form, then submit it. `payment_method` is already on the form (the
 * selected radio), so unlike the blocks sink we don't re-send it.
 */
export const createClassicFormSink = ( {
	$form,
	fingerprint,
	onSubmit,
	onError,
}: ClassicFormSinkContext ): ExpressPaymentResultSink => ( {
	success( result: ExpressPaymentResult ): void {
		if ( result.credentialType === 'confirmation_token' ) {
			appendConfirmationTokenToForm( $form, result.credentialId );
		} else {
			appendPaymentMethodIdToForm( $form, result.credentialId );
		}

		appendExpressPaymentTypeToForm( $form, result.expressPaymentType );
		appendFingerprintInputToForm( $form, fingerprint );
		appendFraudPreventionTokenInputToForm( $form );

		onSubmit();
	},
	error( message: string ): void {
		onError( message );
	},
} );
