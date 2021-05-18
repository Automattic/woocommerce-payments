/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody } from '@wordpress/components';
import { useState } from '@wordpress/element';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import { useEnabledPaymentMethodIds } from 'data';
import OrderableList from 'components/orderable-list';
import PaymentMethod from 'components/orderable-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';
import CreditCardIcon from '../gateway-icons/credit-card';
import GiropayIcon from '../gateway-icons/giropay';
import SofortIcon from '../gateway-icons/sofort';
import SepaIcon from '../gateway-icons/sepa';

const availableMethods = [
	{
		id: 'woocommerce_payments',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		Icon: CreditCardIcon,
	},
	{
		id: 'woocommerce_payments_giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		Icon: GiropayIcon,
	},
	{
		id: 'woocommerce_payments_sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
			'woocommerce-payments'
		),
		Icon: SofortIcon,
	},
	{
		id: 'woocommerce_payments_sepa',
		label: __( 'Direct debit payment', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		Icon: SepaIcon,
	},
];

const PaymentMethods = () => {
	const {
		enabledPaymentMethodIds: enabledMethodIds,
		updateEnabledPaymentMethodIds: updateEnabledMethodIds,
	} = useEnabledPaymentMethodIds();

	const [
		isPaymentMethodsSelectorModalVisible,
		setPaymentMethodsSelectorModalVisible,
	] = useState( false );

	const enabledMethods = enabledMethodIds.map( ( methodId ) =>
		availableMethods.find( ( method ) => method.id === methodId )
	);

	const disabledMethods = availableMethods.filter(
		( method ) => ! enabledMethodIds.includes( method.id )
	);

	const handleDeleteClick = ( itemId ) => {
		updateEnabledMethodIds(
			enabledMethodIds.filter( ( id ) => id !== itemId )
		);
	};

	const handleDragEnd = ( event ) => {
		const { active, over } = event;

		if ( active.id !== over.id ) {
			const oldIndex = enabledMethodIds.indexOf( active.id );
			const newIndex = enabledMethodIds.indexOf( over.id );

			const enabledMethodIdsCopy = [ ...enabledMethodIds ];
			enabledMethodIdsCopy.splice(
				0 > newIndex
					? enabledMethodIdsCopy.length + newIndex
					: newIndex,
				0,
				enabledMethodIdsCopy.splice( oldIndex, 1 )[ 0 ]
			);

			updateEnabledMethodIds( enabledMethodIdsCopy );
		}
	};

	return (
		<>
			{ isPaymentMethodsSelectorModalVisible && (
				<PaymentMethodsSelector
					enabledPaymentMethods={ enabledMethodIds }
					onClose={ () =>
						setPaymentMethodsSelectorModalVisible( false )
					}
				/>
			) }
			<Card className="payment-methods">
				<CardBody className="payment-methods__enabled-methods-container">
					<OrderableList
						onDragEnd={ handleDragEnd }
						className="payment-methods__enabled-methods"
					>
						{ enabledMethods.map(
							( { id, label, description, Icon } ) => (
								<PaymentMethod
									key={ id }
									Icon={ Icon }
									className={ classNames( 'payment-method', {
										'has-icon-border': 'cc' !== id,
									} ) }
									onDeleteClick={
										1 < enabledMethods.length
											? () => handleDeleteClick( id )
											: undefined
									}
									id={ id }
									label={ label }
									description={ description }
								/>
							)
						) }
					</OrderableList>
				</CardBody>
				<CardBody className="payment-methods__available-methods-container">
					<Button
						className="payment-methods__add-payment-method"
						onClick={ () =>
							setPaymentMethodsSelectorModalVisible( true )
						}
						isSecondary
					>
						{ __( 'Add payment method', 'woocommerce-payments' ) }
					</Button>
					<ul className="payment-methods__available-methods">
						{ disabledMethods.map( ( { id, label, Icon } ) => (
							<li
								key={ id }
								className={ classNames(
									'payment-methods__available-method',
									{
										'has-icon-border': 'cc' !== id,
									}
								) }
								aria-label={ label }
							>
								<Icon height="24" width="38" />
							</li>
						) ) }
					</ul>
				</CardBody>
			</Card>
		</>
	);
};

export default PaymentMethods;
