/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CreditCardIcon from './gateway-icons/credit-card';
import BancontactIcon from './gateway-icons/bancontact';
import EpsIcon from './gateway-icons/eps';
import GiropayIcon from './gateway-icons/giropay';
import SofortIcon from './gateway-icons/sofort';
import SepaIcon from './gateway-icons/sepa';
import P24Icon from './gateway-icons/p24';
import IdealIcon from './gateway-icons/ideal';
import BankDebitIcon from './gateway-icons/bank-debit';

export interface PaymentMethodMapEntry {
	id: string;
	label: string;
	description: string;
	icon: () => JSX.Element;
	currencies: string[];
	stripe_key: string;
	allows_manual_capture: boolean;
}

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: CreditCardIcon,
		currencies: [],
		stripe_key: 'card_payments',
		allows_manual_capture: true,
	},
	au_becs_debit: {
		id: 'au_becs_debit',
		label: __( 'BECS Direct Debit', 'woocommerce-payments' ),
		description: __(
			'Bulk Electronic Clearing System — Accept secure bank transfer from Australia.',
			'woocommerce-payments'
		),
		icon: BankDebitIcon,
		currencies: [ 'AUD' ],
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
		currencies: [ 'EUR' ],
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
		currencies: [ 'EUR' ],
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
		currencies: [ 'EUR' ],
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
		currencies: [ 'EUR' ],
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
		currencies: [ 'EUR', 'PLN' ],
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
		currencies: [ 'EUR' ],
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
		currencies: [ 'EUR' ],
		stripe_key: 'sofort_payments',
		allows_manual_capture: false,
	},
	us_bank_account: {
		id: 'us_bank_account',
		label: __( 'US Bank Account Debit (ACH)', 'woocommerce-payments' ),
		description: __(
			'Accept US bank account debits from customers with a US bank account using the Automated Clearing House (ACH) system.',
			'woocommerce-payments'
		),
		Icon: BankDebitIcon,
		currencies: [ 'USD' ],
		stripe_key: 'us_bank_account_ach_payments',
	},
};

export default PaymentMethodInformationObject;
