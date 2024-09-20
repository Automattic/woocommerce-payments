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
import { __, sprintf } from '@wordpress/i18n';
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
		case 'JP':
			// Corporate numbers are not prefixed.
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
		case 'JP':
			return __( 'Corporate Number', 'woocommerce-payments' );
		default:
			return __( 'VAT Number', 'woocommerce-payments' );
	}
};

const getVatTaxIDRequirementHint = () => {
	switch ( wcpaySettings.accountStatus.country ) {
		case 'JP':
			// Leaving this blank intentionally, as I don't know what the requirements are in JP.
			// Better to add this info later than clutter the dialog with vague/assumed legal requirements.
			return __( '', 'woocommerce-payments' );
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
		case 'JP':
			return __(
				'A 13 digit number, for example 1234567890123.',
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
					label={ sprintf(
						__(
							/* translators: %$1$s: tax ID name, e.g. VAT Number, GST Number, Corporate Number */
							"I'm registered for a %1$s",
							'woocommerce-payments'
						),
						getVatTaxIDName()
					) }
					help={ getVatTaxIDRequirementHint() }
				/>
				{ isVatRegistered && (
					// Note: this TextControl is heavily parameterised to support different regions (VAT vs GST vs Corporate Number).
					// Long term, if we implement a dedicated WizardTaskItem component for each tax region, then this component will be simpler.
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
