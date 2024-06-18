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

/**
 * These country-specific getters may belong on server.
 */
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

const getVatTaxName = () => {
	// TODO: do these strings need to be localised?
	switch ( wcpaySettings.accountStatus.country ) {
		// Future: 'GST' for NZ, AU, IN.
		case 'JP':
			return 'JCT'; // AKA consumption tax.
		default:
			return 'VAT'; // AKA value-added tax.
	}
};

const getVatTaxIDName = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'JP':
			return __( 'Corporate Number', 'woocommerce-payments' );
		default:
			return __( 'VAT Number', 'woocommerce-payments' );
	}
};

const getVatTaxIDValidationHint = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'JP':
			return __(
				'This is a 13 digit number, for example 1234567890123.',
				'woocommerce-payments'
			);
		default:
			return __(
				'This is 8 to 12 digits with your country code prefix, for example DE 123456789.',
				'woocommerce-payments'
			);
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
			title={ __( 'Set your tax ID', 'woocommerce-payments' ) }
			className={ null }
		>
			<p className="wcpay-wizard-task__description-element">
				{ __(
					"The information you provide here will be used for all of your account's tax documents.",
					'woocommerce-payments'
				) }
			</p>

			<CollapsibleBody className={ null }>
				<CheckboxControl
					checked={ isVatRegistered }
					onChange={ setVatRegistered }
					label={ __(
						"I'm registered for a VAT number",
						'woocommerce-payments'
					) }
					help={ __(
						"If your sales exceed the relevant threshold, you're required to provide a tax ID.",
						'woocommerce-payments'
					) }
				/>
				{ isVatRegistered && (
					// TODO: refactor this into a little component e.g. VATIdTextControl?
					<TextControl
						label={ getVatTaxIDName() }
						help={ getVatTaxIDValidationHint() }
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
