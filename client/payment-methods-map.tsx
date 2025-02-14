/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import {
	AlipayIcon,
	AffirmIcon,
	AfterpayIcon,
	ClearpayIcon,
	BancontactIcon,
	BankDebitIcon,
	CreditCardIcon,
	EpsIcon,
	GiropayIcon,
	IdealIcon,
	JCBIcon,
	KlarnaIcon,
	P24Icon,
	SepaIcon,
	SofortIcon,
	GrabPayIcon,
	WeChatPayIcon,
} from 'wcpay/payment-methods-icons';

const accountCountry = window.wcpaySettings?.accountStatus?.country || 'US';

export interface PaymentMethodMapEntry {
	id: string;
	label: string;
	description: string;
	icon: ReactImgFuncComponent;
	stripe_key: string;
	allows_manual_capture: boolean;
}

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = {
	card: {
		id: 'card',
		label: __( 'Credit / Debit Cards', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: CreditCardIcon,
		stripe_key: 'card_payments',
		allows_manual_capture: true,
	},
	alipay: {
		id: 'alipay',
		label: __( 'Alipay', 'woocommerce-payments' ),
		description: __(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.',
			'woocommerce-payments'
		),
		icon: AlipayIcon,
		stripe_key: 'alipay_payments',
		allows_manual_capture: false,
	},
	au_becs_debit: {
		id: 'au_becs_debit',
		label: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Bulk Electronic Clearing System — Accept secure bank transfer from Australia.',
			'woocommerce-payments'
		),
		icon: BankDebitIcon,
		stripe_key: 'au_becs_debit_payments',
		allows_manual_capture: false,
	},
	bancontact: {
		id: 'bancontact',
		label: __( 'Bancontact', 'woocommerce-payments' ),
		description: __(
			'Bancontact is a bank redirect payment method offered by more than 80% of online businesses in Belgium.',
			'woocommerce-payments'
		),
		icon: BancontactIcon,
		stripe_key: 'bancontact_payments',
		allows_manual_capture: false,
	},
	eps: {
		id: 'eps',
		label: __( 'EPS', 'woocommerce-payments' ),
		description: __(
			'Accept your payment with EPS — a common payment method in Austria.',
			'woocommerce-payments'
		),
		icon: EpsIcon,
		stripe_key: 'eps_payments',
		allows_manual_capture: false,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		icon: GiropayIcon,
		stripe_key: 'giropay_payments',
		allows_manual_capture: false,
	},
	ideal: {
		id: 'ideal',
		label: __( 'iDEAL', 'woocommerce-payments' ),
		description: __(
			'Expand your business with iDEAL — Netherlands’s most popular payment method.',
			'woocommerce-payments'
		),
		icon: IdealIcon,
		stripe_key: 'ideal_payments',
		allows_manual_capture: false,
	},
	p24: {
		id: 'p24',
		label: __( 'Przelewy24 (P24)', 'woocommerce-payments' ),
		description: __(
			'Accept payments with Przelewy24 (P24), the most popular payment method in Poland.',
			'woocommerce-payments'
		),
		icon: P24Icon,
		stripe_key: 'p24_payments',
		allows_manual_capture: false,
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'SEPA Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		icon: SepaIcon,
		stripe_key: 'sepa_debit_payments',
		allows_manual_capture: false,
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-payments'
		),
		icon: SofortIcon,
		stripe_key: 'sofort_payments',
		allows_manual_capture: false,
	},
	affirm: {
		id: 'affirm',
		label: __( 'Affirm', 'woocommerce-payments' ),
		description: __(
			'Allow customers to pay over time with Affirm.',
			'woocommerce-payments'
		),
		icon: AffirmIcon,
		stripe_key: 'affirm_payments',
		allows_manual_capture: false,
	},
	afterpay_clearpay: {
		id: 'afterpay_clearpay',
		label:
			'GB' === accountCountry
				? __( 'Clearpay', 'woocommerce-payments' )
				: __( 'Afterpay', 'woocommerce-payments' ),
		description:
			'GB' === accountCountry
				? __(
						'Allow customers to pay over time with Clearpay.',
						'woocommerce-payments'
				  )
				: __(
						'Allow customers to pay over time with Afterpay.',
						'woocommerce-payments'
				  ),
		icon: 'GB' === accountCountry ? ClearpayIcon : AfterpayIcon,
		stripe_key: 'afterpay_clearpay_payments',
		allows_manual_capture: false,
	},
	jcb: {
		id: 'jcb',
		label: __( 'JCB', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with JCB, the only international payment brand based in Japan.',
			'woocommerce-payments'
		),
		icon: JCBIcon,
		stripe_key: 'jcb_payments',
		allows_manual_capture: false,
	},
	klarna: {
		id: 'klarna',
		label: __( 'Klarna', 'woocommerce-payments' ),
		description: __(
			'Allow customers to pay over time or pay now with Klarna.',
			'woocommerce-payments'
		),
		icon: KlarnaIcon,
		stripe_key: 'klarna_payments',
		allows_manual_capture: false,
	},
	grabpay: {
		id: 'grabpay',
		label: __( 'GrabPay', 'woocommerce-payments' ),
		description: __(
			'A popular digital wallet for cashless payments in Singapore.',
			'woocommerce-payments'
		),
		icon: GrabPayIcon,
		stripe_key: 'grabpay_payments',
		allows_manual_capture: false,
	},
	wechat_pay: {
		id: 'wechat_pay',
		label: __( 'WeChat Pay', 'woocommerce-payments' ),
		description: __(
			'A digital wallet popular with customers from China.',
			'woocommerce-payments'
		),
		icon: WeChatPayIcon,
		stripe_key: 'wechat_pay_payments',
		allows_manual_capture: false,
	},
};

export default PaymentMethodInformationObject;
