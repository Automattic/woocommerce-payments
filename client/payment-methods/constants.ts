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

enum PAYMENT_METHOD_BRANDS {
	affirm = 'Affirm',
	afterpay_clearpay = 'Afterpay',
	au_becs_debit = 'AU BECS Debit',
	bancontact = 'Bancontact',
	card = 'Card',
	card_present = 'Card Present',
	eps = 'EPS',
	giropay = 'giropay',
	ideal = 'iDEAL',
	link = 'Link',
	p24 = 'P24',
	sepa_debit = 'SEPA Debit',
	sofort = 'SOFORT',
}

export default PAYMENT_METHOD_IDS;
export { PAYMENT_METHOD_BRANDS };
