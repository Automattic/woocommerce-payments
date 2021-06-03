/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon as IconComponent, trash } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';
import { useCallback, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import PaymentDeleteIllustration from '../payment-delete-illustration';
import ConfirmationModal from '../confirmation-modal';

const DeleteButton = ( { id, label, Icon, onClick, className } ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const handleDeleteIconClick = useCallback( () => {
		setIsConfirmationModalOpen( true );
	}, [ setIsConfirmationModalOpen ] );

	const handleDeleteConfirmationClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
		onClick( id );
	}, [ onClick, setIsConfirmationModalOpen, id ] );

	const handleDeleteCancelClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
	}, [ setIsConfirmationModalOpen ] );

	return (
		<>
			{ isConfirmationModalOpen && (
				<ConfirmationModal
					title={ sprintf(
						__(
							/* translators: %1: Name of the payment method being removed */
							'Remove %1$s from checkout',
							'woocommerce-payments'
						),
						label
					) }
					onRequestClose={ handleDeleteCancelClick }
					actions={
						<>
							<Button
								onClick={ handleDeleteConfirmationClick }
								isPrimary
								isDestructive
							>
								{ __( 'Remove', 'woocommerce-payments' ) }
							</Button>
							<Button
								onClick={ handleDeleteCancelClick }
								isSecondary
							>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
						</>
					}
				>
					<PaymentDeleteIllustration
						Icon={ Icon }
						hasBorder={ 'woocommerce_payments' !== id }
					/>
					<p>
						{ interpolateComponents( {
							mixedString: sprintf(
								__(
									'Are you sure you want to remove {{strong}}%s{{/strong}}? ' +
										'Your customers will no longer be able to pay using %s.',
									'woocommerce-payments'
								),
								label,
								label
							),
							components: {
								strong: <strong />,
							},
						} ) }
					</p>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								'You can add it again at any time in {{wooCommercePaymentsLink /}}',
								'woocommerce-payments'
							),
							components: {
								wooCommercePaymentsLink: (
									<a href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments">
										{ __(
											'WooCommerce Payments',
											'woocommerce-payments'
										) }
									</a>
								),
							},
						} ) }
					</p>
				</ConfirmationModal>
			) }
			<Button
				isLink
				aria-label={ sprintf(
					__(
						/* translators: %1: Name of the payment method being removed */
						'Delete %1$s from checkout',
						'woocommerce-payments'
					),
					label
				) }
				className={ className }
				onClick={ handleDeleteIconClick }
			>
				<IconComponent icon={ trash } size={ 24 } />
			</Button>
		</>
	);
};

export default DeleteButton;
