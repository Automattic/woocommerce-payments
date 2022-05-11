/**
 * Each object in the array represents a survey on WPCOM for WCPay.
 * The hierarchy goes: Survey key => Question key => Answer key.
 * */
export const wcPaySurveys = [
	{
		key: 'wcpay-upe-disable-early-access',
		defaultAnswer: { 'why-disable': 'slow-buggy' },
		questions: {
			'why-disable': {
				'slow': 'It is slow',
				'buggy': 'It is buggy',
				'theme-compatibility': 'It does not work with my theme',
				'missing-features':
					'It is missing features I need(describe below)',
				'store-sales': "It hurt my store's sales",
				'poor-customer-experience':
					'It offers poor customer experience',
				other: 'Other(describe below)',
			},
		},
	},
];
