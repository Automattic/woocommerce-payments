/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './item.scss';

const PaymentMethodItem = ( {
	id,
	label,
	description,
	icon,
	onManageClick = () => {},
	onDeleteClick = () => {},
} ) => {
	return (
		<>
			<div
				className={ classNames( 'payment-method__icon-container', {
					'payment-method__icon-container--has-icon': icon,
				} ) }
			>
				{ icon ? icon : null }
			</div>
			<div className="payment-method__text">
				<Button
					isLink
					className="payment-method__label"
					onClick={ () => onManageClick( id ) }
				>
					<strong>{ label }</strong>
				</Button>
				<div className="payment-method__description">
					{ description }
				</div>
			</div>
			<div className="payment-method__actions">
				<Button
					isLink
					className="payment-method__action"
					onClick={ () => onManageClick( id ) }
				>
					{ __( 'Manage', 'woocommerce-payments' ) }
				</Button>
				<Button
					isLink
					className="payment-method__action"
					onClick={ () => onDeleteClick( id ) }
				>
					<Icon icon="trash" size={ 24 } />
				</Button>
			</div>
		</>
	);
};

export default PaymentMethodItem;
