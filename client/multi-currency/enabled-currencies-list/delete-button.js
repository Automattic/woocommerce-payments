/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useCallback, useState } from '@wordpress/element';
import ConfirmationModal from '../../components/confirmation-modal';

// TODO: Delete button and modal should be separated.
// TODO: This removes the item, but the list does not refresh.
const DeleteButton = ( { code, label, onClick, className } ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const handleDeleteIconClick = useCallback( () => {
		setIsConfirmationModalOpen( true );
	}, [ setIsConfirmationModalOpen ] );

	const handleDeleteConfirmationClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
		onClick( code );
	}, [ onClick, setIsConfirmationModalOpen, code ] );

	const handleDeleteCancelClick = useCallback( () => {
		setIsConfirmationModalOpen( false );
	}, [ setIsConfirmationModalOpen ] );

	return (
		<>
			{ isConfirmationModalOpen && (
				<ConfirmationModal
					title={ sprintf(
						__(
							/* translators: %1: Name of the currency being removed */
							'Remove %1$s',
							'woocommerce-payments'
						),
						label
					) }
					onRequestClose={ handleDeleteCancelClick }
					className="enabled-currency-delete-modal"
					actions={
						<>
							<Button
								onClick={ handleDeleteCancelClick }
								isSecondary
							>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button
								onClick={ handleDeleteConfirmationClick }
								isPrimary
								isDestructive
							>
								{ __(
									'Remove currency',
									'woocommerce-payments'
								) }
							</Button>
						</>
					}
				>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								'Please confirm you would like to remove {{currencyName /}} as an enabled currency in your store.',
								'woocommerce-payments'
							),
							components: {
								currencyName: <strong>{ label }</strong>,
							},
						} ) }
					</p>
				</ConfirmationModal>
			) }
			<Button
				isLink
				aria-label={ sprintf(
					__(
						/* translators: %1: Name of the currency being removed */
						'Remove %1$s as an enabled currency',
						'woocommerce-payments'
					),
					label
				) }
				className={ className }
				onClick={ handleDeleteIconClick }
			>
				<Icon icon="trash" />
			</Button>
		</>
	);
};

export default DeleteButton;
