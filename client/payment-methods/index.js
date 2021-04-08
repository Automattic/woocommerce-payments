/** @format */
/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CardHeader,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import OrderableList from 'components/orderable-list';

const availableMethods = [
	{
		id: 'cc',
		label: __( 'Credit card / debit card', 'woocommerce-payments' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		),
		icon: 'c',
	},
	{
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-payments' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-payments'
		),
		icon: 'g',
	},
	{
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-payments' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
			'woocommerce-payments'
		),
		icon: 's',
	},
	{
		id: 'direct-debit',
		label: __( 'Direct debit payment', 'woocommerce-payments' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		),
		icon: 'd',
	},
];

const PaymentMethods = ( {
	enabledMethodIds: initialEnabledMethodIds,
	onEnabledMethodsChange,
} ) => {
	const [ enabledMethodIds, setState ] = useState( initialEnabledMethodIds );
	useEffect( () => {
		onEnabledMethodsChange( enabledMethodIds );
	} );

	const enabledMethods = availableMethods.filter( ( method ) =>
		enabledMethodIds.includes( method.id )
	);
	const disabledMethods = availableMethods.filter(
		( method ) => ! enabledMethodIds.includes( method.id )
	);

	const handleManageClick = () => {};

	const handleDeleteClick = ( itemId ) => {
		setState( enabledMethodIds.filter( ( id ) => id !== itemId ) );
	};

	return (
		<Card className="payment-methods">
			<CardHeader>
				<p>
					<strong>
						{ __( 'Payment methods', 'woocommerce-payments' ) }
					</strong>
				</p>
				<p>
					{ __(
						'Increase your store’s conversion by offering your customers preferred and convenient payment methods. ' +
							'Drag and drop to reorder on checkout.',
						'woocommerce-payments'
					) }
				</p>
			</CardHeader>
			<CardBody>
				<Button>
					{ __( 'Add payment method', 'woocommerce-payments' ) }
				</Button>
				<ul className="payment-methods__available-methods">
					{ disabledMethods.map( ( { id, label } ) => (
						<li key={ id }>{ label }</li>
					) ) }
				</ul>
			</CardBody>
			<CardDivider />
			<CardBody className="payment-methods__enabled-methods-container">
				<OrderableList
					className="payment-methods__enabled-methods"
					items={ enabledMethods }
					onManageClick={ handleManageClick }
					onDeleteClick={ handleDeleteClick }
				/>
			</CardBody>
		</Card>
	);
};

export default PaymentMethods;
