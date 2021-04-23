/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CardHeader,
} from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import OrderableList from 'components/orderable-list';
import PaymentMethod from 'components/orderable-list/payment-method';

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
	const enabledMethods = availableMethods.filter( ( method ) =>
		enabledMethodIds.includes( method.id )
	);

	const disabledMethods = availableMethods.filter(
		( method ) => ! enabledMethodIds.includes( method.id )
	);

	const handleDeleteClick = ( itemId ) => {
		onEnabledMethodIdsChange(
			enabledMethodIds.filter( ( id ) => id !== itemId )
		);
	};

	return (
		<Card className="payment-methods">
			<CardHeader className="payment-methods__header">
				<div className="payment-methods__title">
					{ __( 'Payment methods', 'woocommerce-payments' ) }
				</div>
				<p className="payment-methods__description">
					{ __(
						'Increase your store’s conversion by offering your customers preferred and convenient payment methods. ' +
							'Drag and drop to reorder on checkout.',
						'woocommerce-payments'
					) }
				</p>
			</CardHeader>
			<CardBody className="payment-methods__available-methods-container">
				<Button
					isDefault
					className="payment-methods__add-payment-method"
					onClick={ () =>
						console.debug(
							'Add payment method clicked (not implemented)'
						)
					}
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
			<CardDivider />
			<CardBody className="payment-methods__enabled-methods-container">
				<OrderableList className="payment-methods__enabled-methods">
					{ enabledMethods.map( ( method ) => (
						<PaymentMethod
							key={ method.id }
							className={ classNames(
								'payment-method',
								method.id
							) }
							onDeleteClick={ () =>
								handleDeleteClick( method.id )
							}
							{ ...method }
						/>
					) ) }
				</OrderableList>
			</CardBody>
		</Card>
	);
};

export default PaymentMethods;
