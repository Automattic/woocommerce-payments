/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

/**
 * Internal dependencies
 */

import { JCBIcon } from 'wcpay/payment-methods-icons';

import type { PaymentMethodMapEntry } from './types/payment-methods';

if ( typeof wooPaymentsPaymentMethodDefinitions === 'undefined' ) {
	throw new Error( 'wooPaymentsPaymentMethodDefinitions is undefined' );
}

const PaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = Object.keys( wooPaymentsPaymentMethodDefinitions ).reduce(
	( acc: Record< string, PaymentMethodMapEntry >, key: string ) => {
		acc[ key ] = {
			...wooPaymentsPaymentMethodDefinitions[ key ],
			icon: ( { className }: { className?: string } ) => (
				<img
					src={
						wooPaymentsPaymentMethodDefinitions[ key ]
							.settings_icon_url
					}
					alt={ wooPaymentsPaymentMethodDefinitions[ key ].label }
					className={ classNames(
						'payment-method__icon',
						className
					) }
				/>
			),
		};
		return acc;
	},
	{
		jcb: {
			id: 'jcb',
			label: __( 'JCB', 'woocommerce-payments' ),
			description: __(
				'Let your customers pay with JCB, the only international payment brand based in Japan.',
				'woocommerce-payments'
			),
			icon: JCBIcon,
			currencies: [ 'JPY' ],
			stripe_key: 'jcb_payments',
			allows_manual_capture: false,
			allows_pay_later: false,
			accepts_only_domestic_payment: false,
			settings_icon_url: '',
		},
	}
);

export default PaymentMethodInformationObject;
