/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import React, { useContext, useEffect, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import CollapsibleBody from 'wcpay/components/wizard/collapsible-body';
import WizardTaskItem from 'wcpay/components/wizard/task-item';
import WizardTaskContext from 'wcpay/components/wizard/task/context';
import { VatError, VatFormOnCompleted, VatValidationResult } from '../../types';
import '../style.scss';

/**
 * These country-specific getters may belong on server.
 */
const getVatPrefix = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'AU': // AU ABN numbers are not prefixed. Based on a test lookup at https://abr.business.gov.au/
		case 'JP':
		case 'NZ':
		case 'SG':
			// Countries do not have tax prefixes.
			return '';
		case 'GR':
			return 'EL ';
		case 'CH':
			return 'CHE ';
		default:
			return `${ wcpaySettings.accountStatus.country } `;
	}
};

const getVatTaxIDName = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'AU':
			// Note – AU GST numbers are actually an ABN.
			// https://vatstack.com/articles/australian-business-number-abn-validation
			// https://business.gov.au/registrations/register-for-taxes/tax-registration-for-your-business
			return __( 'ABN', 'woocommerce-payments' );
		case 'JP':
			return __( 'Corporate Number', 'woocommerce-payments' );
		case 'NZ':
			return __( 'IRD Number', 'woocommerce-payments' );
		case 'SG':
			return __(
				'UEN or GST Registration Number',
				'woocommerce-payments'
			);
		default:
			return __( 'VAT Number', 'woocommerce-payments' );
	}
};

const getVatTaxIDRequirementHint = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'AU':
			return __(
				'By inputting your ABN number you confirm that you are going to account for the GST.',
				'woocommerce-payments'
			);
		case 'JP':
			// Leaving this blank intentionally, as I don't know what the requirements are in JP.
			// Better to add this info later than clutter the dialog with vague/assumed legal requirements.
			return __( '', 'woocommerce-payments' );
		case 'NO':
			return __(
				'By inputting your VAT number you confirm you are a Norway VAT registered business and that you are going to account for the VAT.',
				'woocommerce-payments'
			);
		case 'NZ':
			return __(
				'By inputting your IRD number you confirm that you are going to account for the GST.',
				'woocommerce-payments'
			);
		case 'SG':
			return __(
				'By providing your UEN or GST number you confirm you are a Singapore GST registered business and you are going to account for the GST.',
				'woocommerce-payments'
			);
		default:
			// Note: this message is a little alarming and doesn't provide guidance for confused merchants.
			// Logged: https://github.com/Automattic/woocommerce-payments/issues/9161.
			return __(
				"If your sales exceed the VAT threshold for your country, you're required to register for a VAT Number.",
				'woocommerce-payments'
			);
	}
};

const getVatTaxIDValidationHint = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'AU':
			// https://abr.business.gov.au/Help/AbnFormat
			return __(
				'11-digit number, for example 12 345 678 901.',
				'woocommerce-payments'
			);
		case 'JP':
			return __(
				'13-digit number, for example 1234567890123.',
				'woocommerce-payments'
			);
		case 'NZ':
			return __(
				'8-digit or 9-digit number, for example 99-999-999 or 999-999-999.',
				'woocommerce-payments'
			);
		case 'SG':
			return __(
				'Enter your UEN (e.g., 200312345A) or GST Registration Number (e.g., M91234567X).',
				'woocommerce-payments'
			);
		default:
			return __(
				'8 to 12 digits with your country code prefix, for example DE 123456789.',
				'woocommerce-payments'
			);
	}
};

/**
 * Get localized error message for VAT validation errors.
 * Maps known server error codes to localized messages using the appropriate tax ID name.
 * Falls back to the server-provided message for unknown error codes.
 *
 * @param error - The error object containing code and message from the server.
 * @return Localized error message.
 */
const getMappedVatErrorMessage = ( error: VatError ): string => {
	const taxIdName = getVatTaxIDName();

	switch ( error.code ) {
		case 'wcpay_invalid_tax_number':
			return sprintf(
				/* translators: %s: tax ID name, e.g. VAT Number, Corporate Number */
				__(
					'The provided %s failed validation.',
					'woocommerce-payments'
				),
				taxIdName
			);
		case 'wcpay_unsupported_tax_docs_country':
			return __(
				"Your account's country is not supported for tax ID validation.",
				'woocommerce-payments'
			);
		default:
			// Fall back in case the error code is not mapped.
			return error.message;
	}
};

/**
 * A two-step "task" for obtaining merchant's tax details.
 *
 * @param {VatFormOnCompleted} props.onCompleted - Callback to provide tax details on submit.
 */
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

	// Initialize VAT number with prefix when VAT registration is enabled
	useEffect( () => {
		if ( isVatRegistered && vatNumber === '' ) {
			setVatNumber( vatNumberPrefix );
		}
		if ( ! isVatRegistered && vatNumber !== '' ) {
			setVatNumber( '' );
		}
	}, [ isVatRegistered, vatNumber, vatNumberPrefix ] );

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
			setVatValidationError(
				getMappedVatErrorMessage( error as VatError )
			);
		}
	};

	return (
		// Note: the VAT ID name is parameterised in strings below.
		// Long term, it might be better to implement a dedicated WizardTaskItem component for each tax region.
		// This would reduce the amount of branching on country, and make it easier to view or translate the whole UX for each region.
		<WizardTaskItem
			index={ 1 }
			title={ sprintf(
				__(
					/* translators: %$1$s: tax ID name, e.g. VAT Number, GST Number, Corporate Number */
					'Set your %1$s',
					'woocommerce-payments'
				),
				getVatTaxIDName()
			) }
		>
			<p className="wcpay-wizard-task__description-element">
				{ __(
					"The information you provide here will be used for all of your account's tax documents.",
					'woocommerce-payments'
				) }
			</p>

			<CollapsibleBody>
				<CheckboxControl
					className="wcpay-vat-number-task__checkbox"
					checked={ isVatRegistered }
					onChange={ setVatRegistered }
					label={ sprintf(
						__(
							/* translators: %$1$s: tax ID name, e.g. VAT Number, GST Number, Corporate Number */
							'I have a valid %1$s',
							'woocommerce-payments'
						),
						getVatTaxIDName()
					) }
					help={ getVatTaxIDRequirementHint() }
					__nextHasNoMarginBottom
				/>
				{ isVatRegistered && (
					// Note: this TextControl is heavily parameterised to support different regions (VAT vs GST vs Corporate Number).
					// Long term, if we implement a dedicated WizardTaskItem component for each tax region, then this component will be simpler.
					<TextControl
						className="wcpay-vat-number-task__text-control"
						label={ getVatTaxIDName() }
						help={ getVatTaxIDValidationHint() }
						value={ vatNumber }
						onChange={ ( value ) => {
							const prefix = vatNumberPrefix.trim();
							const trimmedValue = value.trim();

							// If the user deletes the prefix, re-add it
							if ( ! trimmedValue.startsWith( prefix ) ) {
								setVatNumber( prefix );
							} else {
								setVatNumber( value );
							}
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				) }

				{ vatValidationError && (
					<Notice
						status="error"
						isDismissible={ false }
						className="vat-number-error"
					>
						{ vatValidationError }
					</Notice>
				) }

				<Button
					variant="primary"
					disabled={ isVatButtonDisabled || isLoading }
					isBusy={ isLoading }
					onClick={ submit }
					__next40pxDefaultSize
				>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};
