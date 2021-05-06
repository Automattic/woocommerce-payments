/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon as IconComponent, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './payment-method.scss';
import { getPaymentMethodSettingsUrl } from '../../utils';

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	onDeleteClick,
} ) => {
	const settingsUrl = getPaymentMethodSettingsUrl( id );

	return (
		<>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<a href={ settingsUrl } className="payment-method__label">
					{ label }
				</a>
				<div className="payment-method__description">
					{ description }
				</div>
			</div>
			<div className="payment-method__actions">
				<a
					href={ settingsUrl }
					className="payment-method__action manage"
				>
					{ __( 'Manage', 'woocommerce-payments' ) }
				</a>
				{ onDeleteClick && (
					<Button
						isLink
						aria-label={ __( 'Delete', 'woocommerce-payments' ) }
						className="payment-method__action delete"
						onClick={ onDeleteClick }
					>
						<IconComponent icon={ trash } size={ 24 } />
					</Button>
				) }
			</div>
		</>
	);
};

export default PaymentMethod;
