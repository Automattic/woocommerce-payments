/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardDivider } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'data';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';
import CreditCardIcon from '../gateway-icons/credit-card';
import GiropayIcon from '../gateway-icons/giropay';
import SofortIcon from '../gateway-icons/sofort';
import SepaIcon from '../gateway-icons/sepa';

const methodsConfiguration = {
	// eslint-disable-next-line camelcase
	woocommerce_payments: {
		id: 'woocommerce_payments',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		Icon: CreditCardIcon,
	},
	// eslint-disable-next-line camelcase
	woocommerce_payments_giropay: {
		id: 'woocommerce_payments_giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		Icon: GiropayIcon,
	},
	// eslint-disable-next-line camelcase
	woocommerce_payments_sofort: {
		id: 'woocommerce_payments_sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
			'woocommerce-payments'
		),
		Icon: SofortIcon,
	},
	// eslint-disable-next-line camelcase
	woocommerce_payments_sepa: {
		id: 'woocommerce_payments_sepa',
		label: __( 'Direct debit payment', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		Icon: SepaIcon,
	},
};

const PaymentMethods = () => {
	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const enabledMethods = availablePaymentMethodIds
		.filter( ( method ) => enabledMethodIds.includes( method ) )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	const disabledMethods = availablePaymentMethodIds
		.filter( ( methodId ) => ! enabledMethodIds.includes( methodId ) )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	const handleDeleteClick = ( itemId ) => {
		updateEnabledMethodIds(
			enabledMethodIds.filter( ( id ) => id !== itemId )
		);
	};

	return (
		<>
			<Card className="payment-methods">
				<CardBody size={ null }>
					<PaymentMethodsList className="payment-methods__enabled-methods">
						{ enabledMethods.map(
							( { id, label, description, Icon } ) => (
								<PaymentMethod
									key={ id }
									Icon={ Icon }
									onDeleteClick={
										1 < enabledMethods.length
											? handleDeleteClick
											: undefined
									}
									id={ id }
									label={ label }
									description={ description }
								/>
							)
						) }
					</PaymentMethodsList>
				</CardBody>
				{ 1 < availablePaymentMethodIds.length ? (
					<>
						<CardDivider />
						<CardBody className="payment-methods__available-methods-container">
							<PaymentMethodsSelector className="payment-methods__add-payment-method" />
							<ul className="payment-methods__available-methods">
								{ disabledMethods.map(
									( { id, label, Icon } ) => (
										<li
											key={ id }
											className={ classNames(
												'payment-methods__available-method',
												{
													'has-icon-border':
														'woocommerce_payments' !==
														id,
												}
											) }
											aria-label={ label }
										>
											<Icon height="24" width="38" />
										</li>
									)
								) }
							</ul>
						</CardBody>
					</>
				) : null }
			</Card>
		</>
	);
};

export default PaymentMethods;
