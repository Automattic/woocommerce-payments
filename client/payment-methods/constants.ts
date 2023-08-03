/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

enum PAYMENT_METHOD_IDS {
	AFFIRM = 'affirm',
	AFTERPAY_CLEARPAY = 'afterpay_clearpay',
	AU_BECS_DEBIT = 'au_becs_debit',
	BANCONTACT = 'bancontact',
	CARD = 'card',
	CARD_PRESENT = 'card_present',
	EPS = 'eps',
	GIROPAY = 'giropay',
	IDEAL = 'ideal',
	LINK = 'link',
	P24 = 'p24',
	SEPA_DEBIT = 'sepa_debit',
	SOFORT = 'sofort',
}

// This constant is used for rendering tooltip titles for payment methods in transaction list and details pages.
// eslint-disable-next-line @typescript-eslint/naming-convention
export const PAYMENT_METHOD_TITLES = {
	ach_credit_transfer: __( 'ACH Credit Transfer', 'woocommerce-payments' ),
	ach_debit: __( 'ACH Debit', 'woocommerce-payments' ),
	acss_debit: __( 'ACSS Debit', 'woocommerce-payments' ),
	affirm: __( 'Affirm', 'woocommerce-payments' ),
	afterpay_clearpay: __( 'Afterpay', 'woocommerce-payments' ),
	alipay: __( 'Alipay', 'woocommerce-payments' ),
	amex: __( 'American Express', 'woocommerce-payments' ),
	au_becs_debit: __( 'AU BECS Debit', 'woocommerce-payments' ),
	bancontact: __( 'Bancontact', 'woocommerce-payments' ),
	card: __( 'Card Payment', 'woocommerce-payments' ),
	card_present: __( 'In-Person Card Payment', 'woocommerce-payments' ),
	diners: __( 'Diners Club', 'woocommerce-payments' ),
	discover: __( 'Discover', 'woocommerce-payments' ),
	eps: __( 'EPS', 'woocommerce-payments' ),
	giropay: __( 'giropay', 'woocommerce-payments' ),
	ideal: __( 'iDEAL', 'woocommerce-payments' ),
	jcb: __( 'JCB', 'woocommerce-payments' ),
	klarna: __( 'Klarna', 'woocommerce-payments' ),
	link: __( 'Link', 'woocommerce-payments' ),
	mastercard: __( 'Mastercard', 'woocommerce-payments' ),
	multibanco: __( 'Multibanco', 'woocommerce-payments' ),
	p24: __( 'P24', 'woocommerce-payments' ),
	sepa_debit: __( 'SEPA Debit', 'woocommerce-payments' ),
	sofort: __( 'SOFORT', 'woocommerce-payments' ),
	stripe_account: __( 'Stripe Account', 'woocommerce-payments' ),
	unionpay: __( 'Union Pay', 'woocommerce-payments' ),
	visa: __( 'Visa', 'woocommerce-payments' ),
	wechat: __( 'WeChat', 'woocommerce-payments' ),
};

// eslint-disable-next-line @typescript-eslint/naming-convention
export const BNPL_COUNTRY_CURRENCY_MAP: Record< string, string > = {
	AU: 'AUD',
	CA: 'CAD',
	GB: 'GBP',
	NZ: 'NZD',
	US: 'USD',
};

export default PAYMENT_METHOD_IDS;
