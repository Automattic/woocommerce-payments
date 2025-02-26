/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';
import DisableConfirmationModal from 'wcpay/disable-confirmation-modal';
import useToggle from 'wcpay/utils/use-toggle';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from 'wcpay/data/constants';

const useIsWCPayEnabled = () => {
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	const IsWCPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWCPayEnabled()
	);

	return [ IsWCPayEnabled, updateIsWCPayEnabled ];
};

const EnableWooPaymentsCheckbox = () => {
	const [ isWCPayEnabled, setIsWCPayEnabled ] = useIsWCPayEnabled();
	const [ isConfirmationModalVisible, toggleModalVisibility ] = useToggle(
		false
	);

	const handleCheckboxClick = ( enableWCPay ) => {
		if ( ! enableWCPay ) {
			toggleModalVisibility();
			return;
		}

		setIsWCPayEnabled( true );

		recordEvent( 'wcpay_gateway_toggle', {
			action: 'enable',
			context: 'wcpay-settings',
		} );
	};

	const handleConfirmDisable = () => {
		setIsWCPayEnabled( false );
		recordEvent( 'wcpay_gateway_toggle', {
			action: 'disable',
			context: 'wcpay-settings',
		} );
		toggleModalVisibility();
	};

	return (
		<>
			<CheckboxControl
				checked={ isWCPayEnabled }
				onChange={ handleCheckboxClick }
				label={ sprintf(
					/* translators: %s: WooPayments */
					__( 'Enable %s', 'woocommerce-payments' ),
					'WooPayments'
				) }
				help={ sprintf(
					/* translators: %s: WooPayments */
					__(
						'When enabled, payment methods powered by %s will appear on checkout.',
						'woocommerce-payments'
					),
					'WooPayments'
				) }
			/>
			{ isConfirmationModalVisible && (
				<DisableConfirmationModal
					onClose={ toggleModalVisibility }
					onConfirm={ handleConfirmDisable }
				/>
			) }
		</>
	);
};

export default EnableWooPaymentsCheckbox;
