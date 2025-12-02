/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * FLAG: PAYMENT_METHODS_LIST
 * New payment methods should be added here, the value should match the definition ID.
 */
enum PAYMENT_METHOD_IDS {
	AFFIRM = 'affirm',
	AFTERPAY_CLEARPAY = 'afterpay_clearpay',
	ALIPAY = 'alipay',
	APPLE_PAY = 'apple_pay',
	AU_BECS_DEBIT = 'au_becs_debit',
	BANCONTACT = 'bancontact',
	CARD = 'card',
	CARD_PRESENT = 'card_present',
	EPS = 'eps',
	GIROPAY = 'giropay',
	GOOGLE_PAY = 'google_pay',
	GRABPAY = 'grabpay',
	IDEAL = 'ideal',
	KLARNA = 'klarna',
	LINK = 'link',
	MULTIBANCO = 'multibanco',
	P24 = 'p24',
	SEPA_DEBIT = 'sepa_debit',
	SOFORT = 'sofort',
	WECHAT_PAY = 'wechat_pay',
}

export enum PAYMENT_METHOD_BRANDS {
	AMEX = 'amex',
	CARTES_BANCAIRES = 'cartes_bancaires',
	DINERS = 'diners',
	DISCOVER = 'discover',
	JCB = 'jcb',
	MASTERCARD = 'mastercard',
	UNIONPAY = 'unionpay',
	VISA = 'visa',
}

// This constant is used for rendering tooltip titles for "payment methods" in transaction list and details pages.
// eslint-disable-next-line @typescript-eslint/naming-convention
export const TRANSACTION_PAYMENT_METHOD_TITLES = {
	ach_credit_transfer: __( 'ACH Credit Transfer', 'woocommerce-payments' ),
	ach_debit: __( 'ACH Debit', 'woocommerce-payments' ),
	acss_debit: __( 'ACSS Debit', 'woocommerce-payments' ),
	amex: __( 'American Express', 'woocommerce-payments' ),
	card: __( 'Card Payment', 'woocommerce-payments' ),
	card_present: __( 'In-Person Card Payment', 'woocommerce-payments' ),
	cartes_bancaires: __( 'Cartes Bancaires', 'woocommerce-payments' ),
	diners: __( 'Diners Club', 'woocommerce-payments' ),
	discover: __( 'Discover', 'woocommerce-payments' ),
	jcb: __( 'JCB', 'woocommerce-payments' ),
	mastercard: __( 'Mastercard', 'woocommerce-payments' ),
	stripe_account: __( 'Stripe Account', 'woocommerce-payments' ),
	unionpay: __( 'Union Pay', 'woocommerce-payments' ),
	visa: __( 'Visa', 'woocommerce-payments' ),
};

export default PAYMENT_METHOD_IDS;
