/**
 * Each exported object represents a survey on WPCOM for WCPay.
 * The hierarchy goes: Survey key => Question key => Answer key.
 * */
export const wcpayDisableEarlyAccessSurvey = {
	key: 'wcpay-upe-disable-early-access',
	questions: {
		'why-disable': {
			'slow-buggy': 'It is slow or buggy',
			'theme-compatibility': 'It does not work with my theme',
			'missing-features': 'It is missing features I need(describe below)',
			'store-sales': "It hurt my store's sales",
			'poor-customer-experience': 'It offers poor customer experience',
			other: 'Other(describe below)',
		},
		comments: { comments: 'Comments (optional)' },
	},
};
