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
import OrderableList from 'components/orderable-list';
import PaymentMethod from 'components/orderable-list/payment-method';
import PaymentMethodsSelector from 'settings/payment-methods-selector';

const availableMethods = [
	{
		id: 'cc',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
	},
	{
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
	},
	{
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
			'woocommerce-payments'
		),
	},
	{
		id: 'sepa',
		label: __( 'Direct debit payment', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
	},
];

const PaymentMethods = ( { enabledMethodIds, onEnabledMethodIdsChange } ) => {
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
		onEnabledMethodIdsChange(
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

			onEnabledMethodIdsChange( enabledMethodIdsCopy );
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
							( { id, label, description } ) => (
								<PaymentMethod
									key={ id }
									className={ classNames(
										'payment-method',
										id
									) }
									onDeleteClick={ () =>
										handleDeleteClick( id )
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
						{ disabledMethods.map( ( { id, label } ) => (
							<li
								key={ id }
								className={ classNames(
									'payment-methods__available-method',
									id
								) }
								aria-label={ label }
							/>
						) ) }
					</ul>
				</CardBody>
			</Card>
		</>
	);
};

export default PaymentMethods;
