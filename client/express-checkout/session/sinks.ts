/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

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
import { getErrorMessageFromNotice } from 'wcpay/express-checkout/utils';
import { transformStripePaymentMethodForStoreApi } from 'wcpay/express-checkout/transformers/stripe-to-wc';
import type {
	ExpressPaymentCredential,
	ExpressPaymentResult,
} from './payment-result';

declare global {
	interface Window {
		wcpayFraudPreventionToken?: string;
	}
}

/**
 * Delivers a credential (or an authorization error) to WooCommerce. Each
 * environment hands the credential over differently; this is the seam where
 * that knowledge - and the exact field names the WCPay server expects - lives.
 *
 * The dynamic paths carry the full {@link ExpressPaymentResult}; the standalone
 * paths only have the bare {@link ExpressPaymentCredential}, hence the type
 * parameter.
 */
export interface ExpressPaymentResultSink<
	T extends ExpressPaymentCredential = ExpressPaymentResult
> {
	success( result: T ): unknown;
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

interface StoreApiSinkContext {
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	api: any;
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	cartApi: any;
	/** The Stripe Express Checkout `confirm` event (billing, shipping, payer, express type). */
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	event: any;
	/** Stripe PaymentMethod types the elements were initialized with. */
	paymentMethodTypes?: string[];
	completePayment: ( redirectUrl: string ) => void;
	abortPayment: ( message: string ) => void;
}

/**
 * Standalone express buttons: the wallet sheet owns the address and totals, so
 * the order is placed through the Store API (bypassing the checkout form) and
 * any required intent is confirmed afterwards. The billing/shipping details
 * come from the wallet `event`, and the express type is whichever wallet the
 * shopper picked - so this works from the bare credential.
 */
export const createStoreApiSink = ( {
	api,
	cartApi,
	event,
	paymentMethodTypes = [],
	completePayment,
	abortPayment,
}: StoreApiSinkContext ): ExpressPaymentResultSink< ExpressPaymentCredential > => ( {
	async success( {
		credentialId,
		credentialType,
	}: ExpressPaymentCredential ): Promise< void > {
		const useConfirmationToken = credentialType === 'confirmation_token';

		try {
			const orderResponse = await cartApi.placeOrder( {
				// adding extension data as a separate action,
				// so that we make it harder for external plugins to modify or intercept checkout data.
				...transformStripePaymentMethodForStoreApi(
					event,
					credentialId,
					useConfirmationToken,
					paymentMethodTypes
				),
				extensions: applyFilters(
					'wcpay.express-checkout.cart-place-order-extension-data',
					{}
				),
			} );

			if ( orderResponse.payment_result.payment_status !== 'success' ) {
				return abortPayment(
					getErrorMessageFromNotice(
						orderResponse.message ??
							orderResponse.payment_result?.payment_details.find(
								( detail: { key: string } ) =>
									detail.key === 'errorMessage'
							)?.value ??
							''
					) ?? ''
				);
			}

			// Extract redirect URL from payment_details if redirect_url is empty
			let redirectUrl = orderResponse.payment_result.redirect_url;
			if ( ! redirectUrl ) {
				const redirectDetail =
					orderResponse.payment_result.payment_details?.find(
						( detail: { key: string } ) => detail.key === 'redirect'
					);
				redirectUrl = redirectDetail?.value || '';
			}

			const confirmationRequest = api.confirmIntent( redirectUrl );

			// `true` means there is no intent to confirm.
			if ( confirmationRequest === true ) {
				completePayment( redirectUrl );
			} else {
				completePayment( await confirmationRequest );
			}
		} catch ( e ) {
			// API errors are not parsed, so we need to do it ourselves.
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			const raw = e as any;
			const error = raw?.json ? await Promise.resolve( raw.json() ) : raw;

			return abortPayment(
				getErrorMessageFromNotice(
					error.message ||
						error.payment_result?.payment_details.find(
							( detail: { key: string } ) =>
								detail.key === 'errorMessage'
						)?.value ||
						__(
							'There was a problem processing the order.',
							'woocommerce-payments'
						)
				) ?? ''
			);
		}
	},
	error( message: string ): void {
		abortPayment( message );
	},
} );
