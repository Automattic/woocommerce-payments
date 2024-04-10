/* eslint-disable jsx-a11y/anchor-has-content */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, Button, ExternalLink } from '@wordpress/components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import { useManualCapture, useCardPresentEligible } from '../../data';
import './style.scss';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import interpolateComponents from '@automattic/interpolate-components';

const ManualCaptureControl = (): JSX.Element => {
	const [
		isManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture() as [ boolean, ( value: boolean ) => void ];
	const [ isCardPresentEligible ] = useCardPresentEligible() as [ boolean ];

	const [
		isManualDepositConfirmationModalOpen,
		setIsManualDepositConfirmationModalOpen,
	] = useState( false );

	const handleCheckboxToggle = ( isChecked: boolean ) => {
		// toggling from "manual" capture to "automatic" capture - no need to show the modal.
		if ( ! isChecked ) {
			setIsManualCaptureEnabled( isChecked );
			return;
		}
		setIsManualDepositConfirmationModalOpen( true );
	};

	const handleModalCancel = () => {
		setIsManualDepositConfirmationModalOpen( false );
	};

	const handleModalConfirmation = () => {
		setIsManualCaptureEnabled( true );
		setIsManualDepositConfirmationModalOpen( false );
	};

	return (
		<>
			<CheckboxControl
				checked={ isManualCaptureEnabled }
				onChange={ handleCheckboxToggle }
				data-testid={ 'capture-later-checkbox' }
				label={ __(
					'Issue an authorization on checkout, and capture later',
					'woocommerce-payments'
				) }
				help={
					<span>
						{ __(
							'Charge must be captured on the order details screen within 7 days of authorization, ' +
								'otherwise the authorization and order will be canceled.',
							'woocommerce-payments'
						) }
						{ isCardPresentEligible
							? interpolateComponents( {
									mixedString: __(
										/** translators: {{a}}: opening and closing anchor tags. The white space at the beginning of the sentence is intentional. */
										' The setting is not applied to {{a}}In-Person Payments{{/a}} (please note that In-Person Payments should be captured within 2 days of authorization).',
										'woocommerce-payments'
									),
									components: {
										a: (
											<ExternalLink href="https://woocommerce.com/in-person-payments/" />
										),
									},
							  } )
							: '' }
					</span>
				}
			/>
			{ isManualDepositConfirmationModalOpen && (
				<ConfirmationModal
					title={ __(
						'Enable manual capture',
						'woocommerce-payments'
					) }
					actions={
						<>
							<Button onClick={ handleModalCancel } isSecondary>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button
								onClick={ handleModalConfirmation }
								isPrimary
							>
								{ __(
									'Enable manual capture',
									'woocommerce-payments'
								) }
							</Button>
						</>
					}
					onRequestClose={ handleModalCancel }
				>
					<strong>
						{ __(
							'Payments must be captured within 7 days or the authorization will expire and money will be returned to the shopper.',
							'woocommerce-payments'
						) }
					</strong>
					<p>
						{ __(
							'Additionally, only card payments support manual capture. Non-card payments will be hidden from checkout.',
							'woocommerce-payments'
						) }
					</p>
					<p>
						{ __(
							'Do you want to continue?',
							'woocommerce-payments'
						) }
					</p>
				</ConfirmationModal>
			) }
		</>
	);
};

export default ManualCaptureControl;
