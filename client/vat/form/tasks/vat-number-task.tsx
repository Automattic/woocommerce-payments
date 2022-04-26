/** @format **/

/**
 * External dependencies
 */
import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import CollapsibleBody from 'wcpay/additional-methods-setup/wizard/collapsible-body';
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'wcpay/additional-methods-setup/wizard/task/context';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { VatError, VatFormOnCompleted, VatValidationResult } from '../../types';
import '../style.scss';

const getVatPrefix = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'GR':
			return 'EL ';
		case 'CH':
			return 'CHE ';
		default:
			return `${ wcpaySettings.accountStatus.country } `;
	}
};

export const VatNumberTask = ( {
	onCompleted,
}: {
	onCompleted: VatFormOnCompleted;
} ): JSX.Element => {
	const { setCompleted } = useContext( WizardTaskContext );

	const [ vatValidationError, setVatValidationError ] = useState<
		string | null
	>( null );
	const [ isLoading, setLoading ] = useState< boolean >( false );

	const [ isVatRegistered, setVatRegistered ] = useState< boolean >( false );
	const [ vatNumber, setVatNumber ] = useState< string >( '' );

	const vatNumberPrefix = getVatPrefix();

	const isVatButtonDisabled =
		isVatRegistered && vatNumber.trimEnd() === vatNumberPrefix.trimEnd();

	// Reset VAT number to default value if prefix is changed.
	if ( ! vatNumber.startsWith( vatNumberPrefix ) ) {
		setVatNumber( vatNumberPrefix );
	}

	const submit = async () => {
		const normalizedVatNumber = isVatRegistered
			? vatNumber.replace( vatNumberPrefix, '' )
			: null;

		let companyName = '';
		let companyAddress = '';

		setVatValidationError( '' );

		try {
			if ( null !== normalizedVatNumber ) {
				setLoading( true );

				const validationResult = await apiFetch< VatValidationResult >(
					{
						path: `/wc/v3/payments/vat/${ encodeURI(
							normalizedVatNumber
						) }`,
					}
				);

				setLoading( false );

				companyName = validationResult.name ?? '';
				companyAddress = validationResult.address ?? '';
			}

			setCompleted( true, 'company-data' );
			onCompleted( normalizedVatNumber, companyName, companyAddress );
		} catch ( error ) {
			setLoading( false );
			setVatValidationError( ( error as VatError ).message );
		}
	};

	return (
		<WizardTaskItem
			index={ 1 }
			title={ __(
				'Update your VAT information',
				'woocommerce-payments'
			) }
			className={ null }
		>
			<p className="wcpay-wizard-task__description-element">
				{ __(
					'VAT information saved on this page will be applied to all of your account’s receipts.',
					'woocommerce-payments'
				) }
			</p>

			<CollapsibleBody className={ null }>
				<CheckboxControl
					checked={ isVatRegistered }
					onChange={ setVatRegistered }
					label={ __(
						'I’m registered for a VAT number',
						'woocommerce-payments'
					) }
					help={ __(
						'If your sales exceed the VAT threshold for your country, you’re required to register for a VAT number.',
						'woocommerce-payments'
					) }
				/>
				{ isVatRegistered && (
					<TextControl
						label={ __( 'VAT Number', 'woocommerce-payments' ) }
						help={ __(
							'This is 8 to 12 digits with your country code prefix, for example DE 123456789.',
							'woocommerce-payments'
						) }
						value={ vatNumber }
						onChange={ setVatNumber }
					/>
				) }

				<Button
					isPrimary
					disabled={ isVatButtonDisabled || isLoading }
					isBusy={ isLoading }
					onClick={ submit }
				>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>

				{ vatValidationError && (
					<Notice
						status="error"
						isDismissible={ false }
						className="vat-number-error"
					>
						{ vatValidationError }
					</Notice>
				) }
			</CollapsibleBody>
		</WizardTaskItem>
	);
};
