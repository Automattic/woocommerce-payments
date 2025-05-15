/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	TextControl,
	TextareaControl,
	SelectControl,
	Notice,
} from '@wordpress/components';
import { merge, some, flatten, isMatchWith } from 'lodash';

/**
 * Internal dependencies.
 */
import '../style.scss';
import { useDisputeEvidence } from 'wcpay/data';
import evidenceFields from './fields';
import { FileUploadControl, UploadedReadOnly } from 'components/file-upload';
import { TestModeNotice } from 'components/test-mode-notice';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import { recordEvent } from 'tracks';
import { getAdminUrl, formatStringValue } from 'wcpay/utils';
import Paragraphs from 'wcpay/components/paragraphs';
import { reasons } from 'wcpay/disputes/strings';
import OrderLink from 'components/order-link';
import DisputeNotice from 'wcpay/payment-details/dispute-details/dispute-notice';
import DisputeDueByDate from 'wcpay/payment-details/dispute-details/dispute-due-by-date';
import { ClickTooltip } from 'wcpay/components/tooltip';
import { HorizontalList } from 'components/horizontal-list';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import { getBankName } from 'utils/charge';
import Accordion from 'components/accordion';
import AccordionBody from 'components/accordion/body';
import AccordionRow from 'components/accordion/row';
import { StepperIndicator } from 'components/stepper';

const DISPUTE_EVIDENCE_MAX_LENGTH = 150000;
const PRODUCT_TYPE_META_KEY = '__product_type';

// Utility: Determine if shipping is required for a given reason
const REASONS_NEED_SHIPPING = [
	'product_unacceptable',
	'product_not_received',
	'fraudulent',
];
const REASONS_NO_SHIPPING = [
	'duplicate',
	'subscription_canceled',
	'credit_not_processed',
];
function needsShipping( reason ) {
	if ( REASONS_NO_SHIPPING.includes( reason ) ) return false;
	if ( REASONS_NEED_SHIPPING.includes( reason ) ) return true;
	return true; // default: needs shipping
}

const MultiStepEvidenceForm = ( {
	fields,
	evidence,
	onChange,
	onFileChange,
	onFileRemove,
	onSave,
	readOnly,
	productType,
	onChangeProductType,
	disputeReason,
} ) => {
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ steps, setSteps ] = useState( () => {
		return needsShipping( disputeReason )
			? [
					__( 'General evidence', 'woocommerce-payments' ),
					__( 'Shipping Information', 'woocommerce-payments' ),
					__( 'Review', 'woocommerce-payments' ),
			  ]
			: [
					__( 'General evidence', 'woocommerce-payments' ),
					__( 'Review', 'woocommerce-payments' ),
			  ];
	} );

	// Update steps if disputeReason or productType changes
	useEffect( () => {
		const newSteps = needsShipping( disputeReason )
			? [
					__( 'General evidence', 'woocommerce-payments' ),
					__( 'Shipping Information', 'woocommerce-payments' ),
					__( 'Review', 'woocommerce-payments' ),
			  ]
			: [
					__( 'General evidence', 'woocommerce-payments' ),
					__( 'Review', 'woocommerce-payments' ),
			  ];
		setSteps( newSteps );
		if ( currentStep > newSteps.length - 1 ) {
			setCurrentStep( newSteps.length - 1 );
		}
	}, [ disputeReason, productType, currentStep ] );

	const handleNext = () => {
		if ( currentStep < steps.length - 1 ) {
			setCurrentStep( currentStep + 1 );
		}
	};

	const handleBack = () => {
		if ( currentStep > 0 ) {
			setCurrentStep( currentStep - 1 );
		}
	};

	const handleSubmit = () => {
		const confirmMessage = __(
			"Are you sure you're ready to submit this evidence? Evidence submissions are final.",
			'woocommerce-payments'
		);
		if ( window.confirm( confirmMessage ) ) {
			onSave( true );
		}
	};

	const updateFormData = ( key, value ) => {
		onChange( key, value );
	};

	const renderStepBody = () => {
		if ( currentStep === steps.length - 1 ) {
			// Review step: show cover letter in plain text using Additional details
			return (
				<>
					<p>
						{ __(
							'Please review the cover letter that will be submitted to the bank based on the information you provided.',
							'woocommerce-payments'
						) }
					</p>
					<div className="cover-letter-preview">
						{ evidence.uncategorized_text ||
							__(
								'No additional details provided.',
								'woocommerce-payments'
							) }
					</div>
				</>
			);
		}
		if ( currentStep === 0 ) {
			return (
				<>
					<div className="evidence-product-type">
						<SelectControl
							label={ __(
								'Product type',
								'woocommerce-payments'
							) }
							value={ productType }
							onChange={ onChangeProductType }
							data-testid={
								'dispute-challenge-product-type-selector'
							}
							options={ [
								{
									label: __(
										'Select one…',
										'woocommerce-payments'
									),
									disabled: true,
									value: '',
								},
								{
									label: __(
										'Physical product',
										'woocommerce-payments'
									),
									value: 'physical_product',
								},
								{
									label: __(
										'Digital product or service',
										'woocommerce-payments'
									),
									value: 'digital_product_or_service',
								},
								{
									label: __(
										'Offline service',
										'woocommerce-payments'
									),
									value: 'offline_service',
								},
								{
									label: __(
										'Multiple product types',
										'woocommerce-payments'
									),
									value: 'multiple',
								},
							] }
							disabled={ readOnly }
						/>
					</div>
					{ fields
						.filter( ( field ) => field.key === 'general' )
						.map( ( section ) => (
							<div key={ section.key }>
								{ section.fields.map( ( field ) => {
									const props = {
										key: field.key,
										label: field.label,
										value: evidence[ field.key ] || '',
										onChange: ( value ) =>
											updateFormData( field.key, value ),
										disabled: readOnly,
										help: field.description,
									};
									switch ( field.type ) {
										case 'file':
											return readOnly ? (
												<UploadedReadOnly
													{ ...props }
													field={ field }
													fileName={
														evidence.metadata?.[
															field.key
														]
													}
													onFileChange={
														onFileChange
													}
													onFileRemove={
														onFileRemove
													}
												/>
											) : (
												<FileUploadControl
													{ ...props }
													field={ field }
													fileName={
														evidence.metadata?.[
															field.key
														]
													}
													onFileChange={
														onFileChange
													}
													onFileRemove={
														onFileRemove
													}
												/>
											);
										case 'text':
											return <TextControl { ...props } />;
										case 'date':
											return (
												<TextControl
													{ ...props }
													type="date"
												/>
											);
										default:
											return (
												<TextareaControl
													{ ...props }
													maxLength={
														field.maxLength
													}
												/>
											);
									}
								} ) }
							</div>
						) ) }
				</>
			);
		}
		if ( currentStep === 1 && steps.length === 3 ) {
			return (
				<>
					{ fields
						.filter(
							( field ) => field.key === 'shipping_information'
						)
						.map( ( section ) => (
							<div key={ section.key }>
								{ section.fields.map( ( field ) => {
									const props = {
										key: field.key,
										label: field.label,
										value: evidence[ field.key ] || '',
										onChange: ( value ) =>
											updateFormData( field.key, value ),
										disabled: readOnly,
										help: field.description,
									};
									switch ( field.type ) {
										case 'file':
											return readOnly ? (
												<UploadedReadOnly
													{ ...props }
													field={ field }
													fileName={
														evidence.metadata?.[
															field.key
														]
													}
													onFileChange={
														onFileChange
													}
													onFileRemove={
														onFileRemove
													}
												/>
											) : (
												<FileUploadControl
													{ ...props }
													field={ field }
													fileName={
														evidence.metadata?.[
															field.key
														]
													}
													onFileChange={
														onFileChange
													}
													onFileRemove={
														onFileRemove
													}
												/>
											);
										case 'text':
											return <TextControl { ...props } />;
										case 'date':
											return (
												<TextControl
													{ ...props }
													type="date"
												/>
											);
										default:
											return (
												<TextareaControl
													{ ...props }
													maxLength={
														field.maxLength
													}
												/>
											);
									}
								} ) }
							</div>
						) ) }
				</>
			);
		}
		return null;
	};

	return (
		<Card size="large">
			<CardHeader>
				<StepperIndicator steps={ steps } currentStep={ currentStep } />
			</CardHeader>
			<CardBody>{ renderStepBody() }</CardBody>
			<CardFooter>
				<div className="evidence-navigation">
					{ currentStep > 0 && (
						<Button isSecondary onClick={ handleBack }>
							{ __( 'Back', 'woocommerce-payments' ) }
						</Button>
					) }
					{ currentStep < steps.length - 1 ? (
						<Button isPrimary onClick={ handleNext }>
							{ __( 'Next', 'woocommerce-payments' ) }
						</Button>
					) : (
						<>
							<Button isPrimary onClick={ handleSubmit }>
								{ __(
									'Submit Evidence',
									'woocommerce-payments'
								) }
							</Button>
							<Button
								isSecondary
								onClick={ () => onSave( false ) }
							>
								{ __(
									'Save for Later',
									'woocommerce-payments'
								) }
							</Button>
						</>
					) }
				</div>
			</CardFooter>
		</Card>
	);
};

/* If description is an array, separate with newline elements. */
const expandHelp = ( description ) => {
	return Array.isArray( description )
		? flatten(
				description.map( ( line, i ) => [ line, <br key={ i } /> ] )
		  )
		: description;
};

export const DisputeEvidenceForm = ( props ) => {
	const {
		fields,
		evidence,
		onChange,
		onFileChange,
		onFileRemove,
		onSave,
		readOnly,
	} = props;

	const { createErrorNotice } = useDispatch( 'core/notices' );
	const { getNotices } = useSelect( 'core/notices' );

	if ( ! fields || ! fields.length ) {
		return null;
	}

	const isEvidenceWithinLengthLimit = ( field, value ) => {
		// Enforce character count for individual evidence field.
		if ( field.maxLength && value.length >= field.maxLength ) {
			return false;
		}

		// Enforce character count for combined evidence fields.
		const totalLength = Object.values( {
			...evidence,
			[ field.key ]: value,
		} ).reduce(
			( acc, cur ) =>
				typeof cur === 'string' ? acc + cur.length : acc,
			0
		);
		if ( totalLength >= DISPUTE_EVIDENCE_MAX_LENGTH ) {
			return false;
		}

		return true;
	};

	const composeDefaultControlProps = ( field ) => ( {
		label: field.label,
		value: evidence[ field.key ] || '',
		onChange: ( value ) => {
			if ( ! isEvidenceWithinLengthLimit( field, value ) ) {
				const errorMessage = __(
					'Reached maximum character count for evidence',
					'woocommerce-payments'
				);
				if (
					! getNotices().some(
						( notice ) => notice.content === errorMessage
					)
				) {
					createErrorNotice( errorMessage );
				}
				return;
			}
			onChange( field.key, value );
		},
		disabled: readOnly,
		help: readOnly && expandHelp( field.description ),
	} );

	const composeFileUploadProps = ( field ) => {
		const fileName =
			( evidence.metadata && evidence.metadata[ field.key ] ) || '';
		const isLoading =
			evidence.isUploading &&
			( evidence.isUploading[ field.key ] || false );
		const error =
			evidence.uploadingErrors &&
			( evidence.uploadingErrors[ field.key ] || '' );
		const isDone = ! isLoading && fileName.length > 0;
		const accept = '.pdf, image/png, image/jpeg';
		return {
			field,
			fileName,
			accept,
			onFileChange,
			onFileRemove,
			disabled: readOnly,
			isLoading,
			isDone,
			error,
			help: readOnly && expandHelp( field.description ),
		};
	};

	const composeFieldControl = ( field ) => {
		const displayAsReadOnly = readOnly && ! evidence[ field.key ];
		switch ( field.type ) {
			case 'file':
				return readOnly ? (
					<UploadedReadOnly
						key={ field.key }
						{ ...composeFileUploadProps( field ) }
					/>
				) : (
					<FileUploadControl
						key={ field.key }
						{ ...composeFileUploadProps( field ) }
					/>
				);
			case 'text':
				return (
					<TextControl
						key={ field.key }
						label={ field.label }
						value={
							displayAsReadOnly
								? __(
										'No information submitted',
										'woocommerce-payments'
								  )
								: null
						}
						disabled={ displayAsReadOnly }
						{ ...( displayAsReadOnly
							? {}
							: composeDefaultControlProps( field ) ) }
					/>
				);
			case 'date':
				return (
					<TextControl
						key={ field.key }
						label={ field.label }
						value={
							displayAsReadOnly
								? __(
										'Date not submitted',
										'woocommerce-payments'
								  )
								: null
						}
						disabled={ displayAsReadOnly }
						{ ...( displayAsReadOnly
							? {}
							: composeDefaultControlProps( field ) ) }
					/>
				);
			default:
				return displayAsReadOnly ? (
					''
				) : (
					<TextareaControl
						key={ field.key }
						maxLength={ field.maxLength }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
		}
	};

	const evidenceSections = fields.map( ( section ) => {
		return (
			<Card size="large" key={ section.key }>
				<CardHeader>{ section.title }</CardHeader>
				<CardBody>
					{ ! readOnly && section.description && (
						<p>{ section.description }</p>
					) }
					{ section.fields.map( composeFieldControl ) }
				</CardBody>
			</Card>
		);
	} );

	const confirmMessage = __(
		"Are you sure you're ready to submit this evidence? Evidence submissions are final.",
		'woocommerce-payments'
	);
	const handleSubmit = () =>
		window.confirm( confirmMessage ) && onSave( true );

	return (
		<>
			{ evidenceSections }
			{ readOnly ? null : (
				<Card size="large">
					<CardBody>
						<p>
							{ __(
								// eslint-disable-next-line max-len
								"When you submit your evidence, we'll format it and send it to the cardholder's bank, then email you once the dispute has been decided.",
								'woocommerce-payments'
							) }
						</p>
						<p>
							<strong>
								{ __(
									'Evidence submission is final.',
									'woocommerce-payments'
								) }
							</strong>{ ' ' }
							{ __(
								'You can also save this evidence for later instead of submitting it immediately.',
								'woocommerce-payments'
							) }{ ' ' }
							<strong>
								{ __(
									'We will automatically submit any saved evidence at the due date.',
									'woocommerce-payments'
								) }
							</strong>
						</p>
					</CardBody>
					<CardFooter>
						{ /* Use wrapping div to keep buttons grouped together. */ }
						<div>
							<Button isPrimary onClick={ handleSubmit }>
								{ __(
									'Submit evidence',
									'woocommerce-payments'
								) }
							</Button>
							<Button
								isSecondary
								onClick={ () => onSave( false ) }
							>
								{ __(
									'Save for later',
									'woocommerce-payments'
								) }
							</Button>
						</div>
					</CardFooter>
				</Card>
			) }
		</>
	);
};

export const DisputeEvidencePage = ( props ) => {
	const {
		isLoading,
		dispute = {},
		productType,
		onChangeProductType,
		...evidenceFormProps
	} = props;
	const readOnly =
		dispute &&
		dispute.status !== 'needs_response' &&
		dispute.status !== 'warning_needs_response';
	const disputeIsAvailable = ! isLoading && dispute.id;

	const readOnlyNotice = (
		<Notice
			className="wcpay-test-mode-notice"
			status="informational"
			isDismissible={ false }
		>
			{ __(
				'Evidence is already submitted. Details below are read-only.',
				'woocommerce-payments'
			) }
		</Notice>
	);

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				<TestModeNotice currentPage="disputes" isDetailsView={ true } />
				<div>
					{ __( 'Dispute not loaded', 'woocommerce-payments' ) }
				</div>
			</Page>
		);
	}

	const disputeReason = formatStringValue(
		reasons[ dispute.reason ]?.display || dispute.reason
	);
	const disputeReasonSummary = reasons[ dispute.reason ]?.summary || [];

	const summaryItems = [
		{
			title: __( 'Dispute Amount', 'woocommerce-payments' ),
			content: formatExplicitCurrency( dispute.amount, dispute.currency ),
		},
		{
			title: __( 'Disputed On', 'woocommerce-payments' ),
			content: dispute.created
				? formatDateTimeFromTimestamp( dispute.created, {
						separator: ', ',
						includeTime: true,
				  } )
				: '–',
		},
		{
			title: __( 'Reason', 'woocommerce-payments' ),
			content: (
				<>
					{ disputeReason }
					{ disputeReasonSummary.length > 0 && (
						<ClickTooltip
							buttonIcon={ <HelpOutlineIcon /> }
							buttonLabel={ __(
								'Learn more',
								'woocommerce-payments'
							) }
							content={
								<div className="dispute-reason-tooltip">
									<p>{ disputeReason }</p>
									<Paragraphs>
										{ disputeReasonSummary }
									</Paragraphs>
									<p>
										<a
											href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</a>
									</p>
								</div>
							}
						/>
					) }
				</>
			),
		},
		{
			title: __( 'Respond By', 'woocommerce-payments' ),
			content: (
				<DisputeDueByDate dueBy={ dispute.evidence_details?.due_by } />
			),
		},
		{
			title: __( 'Order', 'woocommerce-payments' ),
			content: <OrderLink order={ dispute.order } />,
		},
	];

	return (
		<Page maxWidth={ 1032 } className="wcpay-dispute-evidence">
			<TestModeNotice currentPage="disputes" isDetailsView={ true } />
			{ readOnly && ! isLoading && readOnlyNotice }
			<ErrorBoundary>
				<Accordion>
					<AccordionBody highDensity title="Challenge dispute">
						<AccordionRow>
							<div className="evidence-summary__body">
								{ dispute.status && (
									<DisputeNotice
										dispute={ dispute }
										isUrgent={
											dispute.evidence_details?.due_by <
											Date.now() / 1000
										}
										paymentMethod={
											dispute.payment_method_details
												?.type || null
										}
										bankName={ getBankName( dispute ) }
									/>
								) }
								<HorizontalList items={ summaryItems } />
							</div>
						</AccordionRow>
					</AccordionBody>
				</Accordion>
			</ErrorBoundary>
			{ ! isLoading && (
				<ErrorBoundary>
					<div style={ { marginTop: 24 } }>
						<MultiStepEvidenceForm
							{ ...evidenceFormProps }
							readOnly={ readOnly }
							productType={ productType }
							onChangeProductType={ onChangeProductType }
							disputeReason={ dispute.reason }
						/>
					</div>
				</ErrorBoundary>
			) }
		</Page>
	);
};

/**
 * Retrieves product type from the dispute.
 *
 * @param {Object?} dispute Dispute object
 * @return {string} dispute product type
 */
const getDisputeProductType = ( dispute ) => {
	if ( ! dispute ) {
		return '';
	}

	let productType = dispute.metadata[ PRODUCT_TYPE_META_KEY ] || '';

	// Fallback to `multiple` when evidence submitted but no product type meta.
	if (
		! productType &&
		dispute.evidence_details &&
		dispute.evidence_details.has_evidence
	) {
		productType = 'multiple';
	}

	return productType;
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState();
	const [ loading, setLoading ] = useState( false );
	const [ evidence, setEvidence ] = useState( {} ); // Evidence to update.
	const [ redirectAfterSave, setRedirectAfterSave ] = useState( false );

	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
	} = useDispatch( 'core/notices' );

	const pristine =
		! dispute ||
		isMatchWith(
			dispute.evidence,
			evidence,
			( disputeValue, formValue ) => {
				// Treat null and '' as equal values.
				if ( disputeValue === null && ! formValue ) {
					return true;
				}
			}
		);

	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( pristine || redirectAfterSave ) {
			return;
		}

		return __(
			'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
			'woocommerce-payments'
		);
	} );

	useEffect( confirmationNavigationCallback, [
		pristine,
		confirmationNavigationCallback,
		redirectAfterSave,
	] );

	useEffect( () => {
		const fetchDispute = async () => {
			setLoading( true );
			try {
				setDispute( await apiFetch( { path } ) );
			} finally {
				setLoading( false );
			}
		};

		fetchDispute();
	}, [ setLoading, setDispute, path ] );

	const updateEvidence = ( key, value ) =>
		setEvidence( ( e ) => ( { ...e, [ key ]: value } ) );
	const updateDispute = ( updates = {} ) =>
		setDispute( ( d ) => merge( {}, d, updates ) );
	const isUploadingEvidence = () => some( dispute.isUploading );

	const doRemoveFile = ( key ) => {
		updateEvidence( key, '' );
		updateDispute( {
			metadata: { [ key ]: '' },
			uploadingErrors: { [ key ]: '' },
			fileSize: { [ key ]: 0 },
		} );
	};

	const fileSizeExceeded = ( latestFileSize ) => {
		const fileSizeLimitInBytes = 4500000;
		const fileSizes = dispute.fileSize
			? Object.values( dispute.fileSize )
			: [];
		const totalFileSize =
			fileSizes.reduce( ( acc, fileSize ) => acc + fileSize, 0 ) +
			latestFileSize;
		if ( fileSizeLimitInBytes < totalFileSize ) {
			createInfoNotice(
				__(
					"The files you've attached to this dispute as evidence will exceed the limit for a " +
						"dispute's total size. Try using smaller files as evidence. Hint: if you've attached " +
						'images, you might want to try providing them in lower resolutions.',
					'woocommerce-payments'
				)
			);
			return true;
		}
	};

	const doUploadFile = async ( key, file ) => {
		if ( ! file ) {
			return;
		}

		if ( fileSizeExceeded( file.size ) ) {
			return;
		}

		recordEvent( 'wcpay_dispute_file_upload_started', {
			type: key,
		} );

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', 'dispute_evidence' );

		// Set request status for UI.
		updateDispute( {
			metadata: { [ key ]: '' },
			isUploading: { [ key ]: true },
			uploadingErrors: { [ key ]: '' },
		} );

		// Force reload evidence components.
		updateEvidence( key, '' );

		try {
			const uploadedFile = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );
			// Store uploaded file name in metadata to display in submitted evidence or saved for later form.
			updateDispute( {
				metadata: { [ key ]: uploadedFile.filename },
				isUploading: { [ key ]: false },
				fileSize: { [ key ]: uploadedFile.size },
			} );
			updateEvidence( key, uploadedFile.id );

			recordEvent( 'wcpay_dispute_file_upload_success', {
				type: key,
			} );
		} catch ( err ) {
			recordEvent( 'wcpay_dispute_file_upload_failed', {
				message: err.message,
			} );

			updateDispute( {
				metadata: { [ key ]: '' },
				isUploading: { [ key ]: false },
				uploadingErrors: { [ key ]: err.message },
			} );

			// Force reload evidence components.
			updateEvidence( key, '' );
		}
	};

	const handleSaveSuccess = ( submit ) => {
		const message = submit
			? __( 'Evidence submitted!', 'woocommerce-payments' )
			: __( 'Evidence saved!', 'woocommerce-payments' );

		recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_success'
				: 'wcpay_dispute_save_evidence_success'
		);
		/*
			We rely on WC-Admin Transient notices to display success message.
			https://github.com/woocommerce/woocommerce-admin/tree/master/client/layout/transient-notices.
		*/
		createSuccessNotice( message, {
			actions: [
				{
					label: submit
						? __(
								'View submitted evidence',
								'woocommerce-payments'
						  )
						: __(
								'Return to evidence submission',
								'woocommerce-payments'
						  ),
					url: getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes/challenge',
						id: query.id,
					} ),
				},
			],
		} );

		setRedirectAfterSave( true );
	};

	useEffect( () => {
		if ( redirectAfterSave && pristine ) {
			const href = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
				filter: 'awaiting_response',
			} );
			window.location.replace( href );
		}
	}, [ redirectAfterSave, pristine ] );

	const handleSaveError = ( err, submit ) => {
		recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_failed'
				: 'wcpay_dispute_save_evidence_failed'
		);

		const message = submit
			? __( 'Failed to submit evidence. (%s)', 'woocommerce-payments' )
			: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
		createErrorNotice( sprintf( message, err.message ) );
	};

	const { updateDispute: updateDisputeInStore } = useDisputeEvidence();

	const doSave = async ( submit ) => {
		// Prevent submit if upload is in progress.
		if ( isUploadingEvidence() ) {
			createInfoNotice(
				__(
					'Please wait until file upload is finished',
					'woocommerce-payments'
				)
			);
			return;
		}

		setLoading( true );

		try {
			recordEvent(
				submit
					? 'wcpay_dispute_submit_evidence_clicked'
					: 'wcpay_dispute_save_evidence_clicked'
			);

			const { metadata } = dispute;
			const updatedDispute = await apiFetch( {
				path,
				method: 'post',
				data: {
					// Send full evidence, as submission does not appear to work without new evidence despite being optional.
					evidence: { ...dispute.evidence, ...evidence },
					metadata,
					submit,
				},
			} );
			setDispute( updatedDispute );
			setEvidence( {} );
			handleSaveSuccess( submit );
			updateDisputeInStore( updatedDispute );
		} catch ( err ) {
			handleSaveError( err, submit );
		} finally {
			setLoading( false );
		}
	};

	const productType = getDisputeProductType( dispute );
	const updateProductType = ( newProductType ) => {
		const properties = {
			selection: newProductType,
		};
		recordEvent( 'wcpay_dispute_product_selected', properties );
		updateDispute( {
			metadata: { [ PRODUCT_TYPE_META_KEY ]: newProductType },
		} );
	};

	const disputeReason = dispute && dispute.reason;
	const fieldsToDisplay = useMemo(
		() => evidenceFields( disputeReason, productType ),
		[ disputeReason, productType ]
	);

	return (
		<DisputeEvidencePage
			isLoading={ loading }
			dispute={ dispute }
			evidence={
				dispute
					? {
							...dispute.evidence,
							...evidence,
							metadata: dispute.metadata || {},
							isUploading: dispute.isUploading || {},
							uploadingErrors: dispute.uploadingErrors || {},
					  }
					: {}
			}
			onChange={ updateEvidence }
			onFileChange={ doUploadFile }
			onFileRemove={ doRemoveFile }
			onSave={ doSave }
			productType={ productType }
			onChangeProductType={ updateProductType }
			fields={ fieldsToDisplay }
		/>
	);
};
