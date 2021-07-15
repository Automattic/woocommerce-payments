/** @format */
/**
 * External dependencies
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import CheckboxList from 'components/checkbox-list';

const AddPaymentMethodsModal = ( {
	disabledMethods,
	addMethodIdsCallback,
	onRequestClose = () => {},
} ) => {
	const [ methodIdsToAdd, setMethodIdsToAdd ] = useState( [] );
	const wrappedOnRequestClose = () => {
		setMethodIdsToAdd( [] );
		onRequestClose();
	};
	const wrappedAddMethodIdsCallback = ( idsToAdd ) => {
		addMethodIdsCallback( idsToAdd );
		onRequestClose();
	};
	const onMethodIdsToAddChange = ( idsToAdd ) => {
		setMethodIdsToAdd( idsToAdd );
	};

	const styledDisabledMethods = disabledMethods.map( ( method ) => {
		const styledLabel = (
			<>
				<div>{ method.label }</div>
				<div>Missing fees i</div>
			</>
		);

		return {
			...method,
			label: styledLabel,
		};
	} );

	return (
		<Modal
			className="add-payment-methods-modal"
			title={ __( 'Add payment methods', 'woocommerce-payments' ) }
			onRequestClose={ wrappedOnRequestClose }
			isDismissible={ false }
		>
			<p>
				{ __(
					"Increase your store's conversion by offering your customers preferred and convenient payment methods.",
					'woocommerce-payments'
				) }
			</p>
			<CheckboxList
				items={ styledDisabledMethods }
				checkedIds={ methodIdsToAdd }
				onCheckedIdsChange={ onMethodIdsToAddChange }
			/>
			<Button
				isPrimary
				onClick={ () => wrappedAddMethodIdsCallback( methodIdsToAdd ) }
			>
				{ __( 'Add selected', 'woocommerce-payments' ) }
			</Button>
			<Button onClick={ wrappedOnRequestClose }>
				{ __( 'Cancel', 'woocommerce-payments' ) }
			</Button>
		</Modal>
	);
};

export default AddPaymentMethodsModal;
