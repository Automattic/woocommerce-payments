/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Modal, Button, Icon } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { HorizontalRule } from '@wordpress/primitives';
import { useCallback, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './delete-button.scss';
// TODO: Delete button and modal should be separated.
// TODO: This removes the item, but the list does not refresh.
// TODO: Should have standard modal footer. Remove horizontal rule.
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
				<Modal
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
					<HorizontalRule className="enabled-currency-delete-modal__separator" />
					<div className="enabled-currency-delete-modal__footer">
						<Button onClick={ handleDeleteCancelClick } isSecondary>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button
							onClick={ handleDeleteConfirmationClick }
							isPrimary
							isDestructive
						>
							{ __( 'Remove currency', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
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
				<Icon icon="trash" size={ 24 } />
			</Button>
		</>
	);
};

export default DeleteButton;
