/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './payment-method.scss';

const PaymentMethod = ( {
	label,
	description,
	onManageClick,
	onDeleteClick,
} ) => {
	return (
		<>
			<div className="payment-method__icon" />
			<div className="payment-method__text">
				<Button
					isLink
					className="payment-method__label"
					onClick={ onManageClick }
				>
					{ label }
				</Button>
				<div className="payment-method__description">
					{ description }
				</div>
			</div>
			<div className="payment-method__actions">
				<Button
					isLink
					className="payment-method__action manage"
					onClick={ onManageClick }
				>
					{ __( 'Manage', 'woocommerce-payments' ) }
				</Button>
				<Button
					isLink
					aria-label={ __( 'Delete', 'woocommerce-payments' ) }
					className="payment-method__action delete"
					onClick={ onDeleteClick }
				>
					<Icon icon={ trash } size={ 24 } />
				</Button>
			</div>
		</>
	);
};

export default PaymentMethod;
