/** @format **/

/**
 * External dependencies
 */
import { Button, Modal, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
//import './style.scss';

const onChange = ( e ) => {
	console.log( e.target );
};

const AvailableCurrenciesModal = ( {
	availableCurrencies,
	enabledCurrencies,
	defaultCurrency,
	onClose,
	onSubmit,
} ) => {
	const availableKeys = Object.keys( availableCurrencies );

	return (
		<Modal
			title={ __( 'Add currencies', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-changeme"
		>
			{ availableKeys.map( ( key ) => {
				console.log( key );
				if ( key === defaultCurrency ) {
					return '';
				}

				return (
					<CheckboxControl
						key={ key }
						label={ availableCurrencies[ key ].name }
						//name={ name }
						checked={ enabledCurrencies[ key ] }
						onChange={ onChange }
						//disabled={ disabled }
					/>
				);
			} ) }

			<div className="wcpay-available-currencies-modal__footer">
				<Button isSecondary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
				<Button
					isPrimary
					onClick={ onSubmit }
					// isBusy={ inProgress }
					// disabled={ inProgress }
				>
					{ __( 'Add currencies', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default AvailableCurrenciesModal;
