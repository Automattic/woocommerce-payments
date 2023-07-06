/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';

export default {
	// Error codes/messages from Stripe. See: https://stripe.com/docs/api/accounts/object#account_object-requirements-errors-suggestion-message
	errors: {
		invalid_address_city_state_postal_code: __(
			'The combination of the city, state, and postal code in the provided address could not be validated.',
			'woocommerce-payments'
		),
		invalid_street_address: __(
			'The street name and/or number for the provided address could not be validated.',
			'woocommerce-payments'
		),
		invalid_tos_acceptance: createInterpolateElement(
			__(
				'The existing terms of service signature has been invalidated because the account’s tax ID has changed. The account needs to accept the terms of service again. For more information, see <a>this documentation</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/update-verified-information"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		invalid_representative_country: __(
			'The representative must have an address in the same country as the company.',
			'woocommerce-payments'
		),
		verification_document_address_mismatch: __(
			'The address on the document did not match the address on the account. Upload a document with a matching address or update the address on the account.',
			'woocommerce-payments'
		),
		verification_document_address_missing: __(
			'The company address was missing on the document. Upload a document that includes the address.',
			'woocommerce-payments'
		),
		verification_document_corrupt: __(
			'The uploaded file for the document was invalid or corrupt. Upload a new file of the document.',
			'woocommerce-payments'
		),
		verification_document_country_not_supported: __(
			'The provided document was from an unsupported country.',
			'woocommerce-payments'
		),
		verification_document_dob_mismatch: __(
			'The date of birth (DOB) on the document did not match the DOB on the account. Upload a document with a matching DOB or update the DOB on the account.',
			'woocommerce-payments'
		),
		verification_document_duplicate_type: __(
			'The same type of document was used twice. Two unique types of documents are required for verification. Upload two different documents.',
			'woocommerce-payments'
		),
		verification_document_expired: __(
			'The document could not be used for verification because it has expired. If it’s an identity document, its expiration date must be after the date the document was submitted. If it’s an address document, the issue date must be within the last six months.',
			'woocommerce-payments'
		),
		verification_document_failed_copy: __(
			'The document could not be verified because it was detected as a copy (e.g., photo or scan). Upload the original document.',
			'woocommerce-payments'
		),
		verification_document_failed_greyscale: __(
			'The document could not be used for verification because it was in greyscale. Upload a color copy of the document.',
			'woocommerce-payments'
		),
		verification_document_failed_other: createInterpolateElement(
			__(
				'The document could not be verified for an unknown reason. Ensure that the document follows the <a>guidelines for document uploads</a>',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/identity-verification-api#acceptable-verification-documents"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_document_failed_test_mode: createInterpolateElement(
			__(
				'A test data helper was supplied to simulate verification failure. Refer to the documentation for <a>test file tokens</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/testing#test-file-tokens"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_document_fraudulent: __(
			'The document was identified as altered or falsified.',
			'woocommerce-payments'
		),
		verification_document_id_number_mismatch: __(
			'The company ID number on the account could not be verified. Correct any errors in the ID number field or upload a document that includes the ID number.',
			'woocommerce-payments'
		),
		verification_document_id_number_missing: __(
			'The company ID number was missing on the document. Upload a document that includes the ID number.',
			'woocommerce-payments'
		),
		verification_document_incomplete: __(
			'The document was cropped or missing important information. Upload a complete scan of the document.',
			'woocommerce-payments'
		),
		verification_document_invalid: createInterpolateElement(
			__(
				'The uploaded file was not one of the valid document types. Ensure that the document follows the <a>guidelines for document uploads</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/identity-verification-api#acceptable-verification-documents"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_document_issue_or_expiry_date_missing: __(
			'The issue or expiry date is missing on the document. Upload a document that includes the issue and expiry dates.'
		),
		verification_document_manipulated: __(
			'The document was identified as altered or falsified.',
			'woocommerce-payments'
		),
		verification_document_missing_back: __(
			'The uploaded file was missing the back of the document. Upload a complete scan of the document.',
			'woocommerce-payments'
		),
		verification_document_missing_front: __(
			'The uploaded file was missing the front of the document. Upload a complete scan of the document.',
			'woocommerce-payments'
		),
		verification_document_name_mismatch: __(
			'The name on the document did not match the name on the account. Upload a document with a matching name or update the name on the account.',
			'woocommerce-payments'
		),
		verification_document_name_missing: __(
			'The company name was missing on the document. Upload a document that includes the company name.',
			'woocommerce-payments'
		),
		verification_document_nationality_mismatch: __(
			'The nationality on the document did not match the person’s stated nationality. Update the person’s stated nationality, or upload a document that matches it.',
			'woocommerce-payments'
		),
		verification_document_not_readable: createInterpolateElement(
			__(
				'The document could not be read. Ensure that the document follows the <a>guidelines for document uploads</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/identity-verification-api#acceptable-verification-documents"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_document_not_signed: __(
			'A valid signature is missing on the document. Upload a document that includes a valid signature.',
			'woocommerce-payments'
		),
		verification_document_not_uploaded: __(
			'No document was uploaded. Upload the document again.',
			'woocommerce-payments'
		),
		verification_document_photo_mismatch: __(
			'The document was identified as altered or falsified.',
			'woocommerce-payments'
		),
		verification_document_too_large: __(
			'The uploaded file exceeded the 10 MB size limit. Resize the document and upload the new file.',
			'woocommerce-payments'
		),
		verification_document_type_not_supported: createInterpolateElement(
			__(
				'The provided document type was not accepted. Ensure that the document follows the <a>guidelines for document uploads</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://stripe.com/docs/connect/identity-verification-api#acceptable-verification-documents"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_failed_address_match: __(
			'The address on the account could not be verified. Correct any errors in the address field or upload a document that includes the address.',
			'woocommerce-payments'
		),
		verification_failed_business_iec_number: __(
			'The Importer Exporter Code (IEC) number could not be verified. Correct any errors in the company’s IEC number field. (India only)',
			'woocommerce-payments'
		),
		verification_failed_document_match: __(
			'The document could not be verified. Upload a document that includes the company name, ID number, and address fields.',
			'woocommerce-payments'
		),
		verification_failed_id_number_match: __(
			'The company ID number on the account could not be verified. Correct any errors in the ID number field or upload a document that includes the ID number.',
			'woocommerce-payments'
		),
		verification_failed_keyed_identity: __(
			'The person’s keyed-in identity information could not be verified. Correct any errors or upload a document that matches the identity fields (e.g., name and date of birth) entered.',
			'woocommerce-payments'
		),
		verification_failed_keyed_match: __(
			'The keyed-in information on the account could not be verified. Correct any errors in the company name, ID number, or address fields. You can also upload a document that includes those fields.',
			'woocommerce-payments'
		),
		verification_failed_name_match: __(
			'The company name on the account could not be verified. Correct any errors in the company name field or upload a document that includes the company name.',
			'woocommerce-payments'
		),
		verification_failed_residential_address: __(
			'We could not verify that the person resides at the provided address. The address must be a valid physical address where the individual resides and cannot be a P.O. Box.',
			'woocommerce-payments'
		),
		verification_failed_tax_id_match: __(
			'The tax ID on the account cannot be verified by the IRS. Either correct any possible errors in the company name or tax ID, or upload a document that contains those fields.',
			'woocommerce-payments'
		),
		verification_failed_tax_id_not_issued: createInterpolateElement(
			__(
				'The tax ID on the account was not recognized by the IRS. Refer to the support article for <a>newly-issued tax ID numbers</a>.',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href="https://support.stripe.com/questions/newly-issued-us-tax-id-number-tin-not-verifying"
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		),
		verification_failed_other: __(
			'Verification failed for an unknown reason. Correct any errors and resubmit the required fields.',
			'woocommerce-payments'
		),
		verification_missing_owners: __(
			'We have identified owners that haven’t been added on the account. Add any missing owners to the account.',
			'woocommerce-payments'
		),
		verification_missing_executives: __(
			'We have identified executives that haven’t been added on the account. Add any missing executives to the account.',
			'woocommerce-payments'
		),
		verification_requires_additional_memorandum_of_associations: __(
			'We have identified holding companies with significant percentage ownership. Upload a Memorandum of Association for each of the holding companies.',
			'woocommerce-payments'
		),
		invalid_dob_age_under_18: __(
			'Underage. Age must be at least 18.',
			'woocommerce-payments'
		),
	},
	// Strings needed for the progressive onboarding related tasks.
	po_tasks: {
		no_payment_14_days: {
			title: __(
				'Please add your bank details to keep selling',
				'woocommerce-payments'
			),
			description: ( dueDate: string ): React.ReactElement | string => {
				return createInterpolateElement(
					sprintf(
						__(
							'You have time until <strong>%s</strong> to make your first sale without undergoing full business verification. Take advantage of this time window and start selling now.',
							'woocommerce-payments'
						),
						dueDate
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __( 'Set up deposits', 'woocommerce-payments' ),
		},
		no_payment_30_days: {
			title: __(
				'Payments paused! Verify your bank details to reactivate.',
				'woocommerce-payments'
			),
			description: (): React.ReactElement | string => {
				return createInterpolateElement(
					__(
						'You have reached the <strong>30-day limit</strong> for early selling access. In order to reactivate payments, please verify your bank details.',
						'woocommerce-payments'
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __( 'Verify bank details', 'woocommerce-payments' ),
		},
		after_payment: {
			title: __(
				'Verify your bank account to start receiving deposits',
				'woocommerce-payments'
			),
			description: ( dueDate: string ): React.ReactElement | string => {
				return createInterpolateElement(
					sprintf(
						__(
							'Add the required details by <strong>%s</strong> or <strong>before reaching $5,000</strong> in sales to avoid a disruption in payments.',
							'woocommerce-payments'
						),
						dueDate
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __(
				'Start receiving deposits',
				'woocommerce-payments'
			),
		},
		balance_rising: {
			title: __(
				'Verify your bank account to start receiving deposits',
				'woocommerce-payments'
			),
			description: ( dueDate: string ): React.ReactElement | string => {
				return createInterpolateElement(
					sprintf(
						__(
							'To ensure a smooth payments process, please make sure to confirm your bank details by <strong>%s</strong> or before you reach <strong>$5,000</strong> in sales.',
							'woocommerce-payments'
						),
						dueDate
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __(
				'Start receiving deposits',
				'woocommerce-payments'
			),
		},
		near_threshold: {
			title: __( 'Verify your bank details', 'woocommerce-payments' ),
			description: ( dueDate: string ): React.ReactElement | string => {
				return createInterpolateElement(
					sprintf(
						__(
							'Verify your bank details by <strong>%s</strong> or before reaching <strong>$5,000</strong> in sales to avoid a disruption in payments.',
							'woocommerce-payments'
						),
						dueDate
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __( 'Set up deposits', 'woocommerce-payments' ),
		},
		threshold_reached: {
			title: __(
				'Payments paused! Verify your bank details to reactivate.',
				'woocommerce-payments'
			),
			description: ( dueDate: string ): React.ReactElement | string => {
				return createInterpolateElement(
					sprintf(
						__(
							'<strong>You have reached the deposit threshold of $5,000.00. Please verify your bank account now to reactivate payments.</strong> Your customers can no longer make purchases on your store until your account is verified.',
							'woocommerce-payments'
						),
						dueDate
					),
					{
						strong: <strong />,
					}
				);
			},
			action_label: __( 'Verify bank details', 'woocommerce-payments' ),
		},
	},
};
