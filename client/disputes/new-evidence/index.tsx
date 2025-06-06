/** @format **/

/**
 * External dependencies
 */
import React, { useState, useEffect, useMemo } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import { recordEvent } from 'tracks';
import { TestModeNotice } from 'components/test-mode-notice';
import ErrorBoundary from 'components/error-boundary';
import Paragraphs from 'components/paragraphs';
import { reasons } from 'wcpay/disputes/strings';
import OrderLink from 'components/order-link';
import DisputeNotice from 'payment-details/dispute-details/dispute-notice';
import DisputeDueByDate from 'payment-details/dispute-details/dispute-due-by-date';
import { ClickTooltip } from 'components/tooltip';
import { HorizontalList } from 'components/horizontal-list';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import { getBankName } from 'utils/charge';
import CustomerDetails from './customer-details';
import ProductDetails from './product-details';
import RecommendedDocuments from './recommended-documents';
import InlineNotice from 'components/inline-notice';
import ShippingDetails from './shipping-details';
import CoverLetter from './cover-letter';

/**
 * Internal dependencies.
 */
import { Button, HorizontalRule } from 'wcpay/components/wp-components-wrapped';
import { getAdminUrl } from 'wcpay/utils';
import { StepperPanel } from 'wcpay/components/stepper';
import {
	Accordion,
	AccordionBody,
	AccordionRow,
} from 'wcpay/components/accordion';
import Page from 'wcpay/components/page';

import './style.scss';
import { createInterpolateElement } from '@wordpress/element';

// --- Utility: Determine if shipping is required for a given reason ---
const ReasonsNeedShipping = [
	'product_unacceptable',
	'product_not_received',
	'fraudulent',
];

const ReasonsNoShipping = [
	'duplicate',
	'subscription_canceled',
	'credit_not_processed',
];

// Stepper headings/subheadings
const steps = [
	{
		heading: "Let's gather the basics",
		subheading:
			'To make a stronger case, please provide as much info as possible. We prefilled some fields for you, please double check and upload all the necessary documents.',
	},
	{
		heading: 'Shipping details',
		subheading: 'Please make sure all the shipping information is correct.',
	},
	{
		heading: 'Review the cover letter',
		subheading:
			'Please review the cover letter that will be submitted to the bank based on the information you provided. You can make changes to it or add additional details.',
	},
];

function needsShipping( reason: string | undefined ) {
	if ( ! reason ) return true;
	if ( ReasonsNoShipping.includes( reason ) ) return false;
	if ( ReasonsNeedShipping.includes( reason ) ) return true;
	return true;
}

// --- Main Component ---
export default ( { query }: { query: { id: string } } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;
	const [ dispute, setDispute ] = useState< any >();
	const [ evidence, setEvidence ] = useState< any >( {} );
	const [ productType, setProductType ] = useState< string >( '' );
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ isAccordionOpen, setIsAccordionOpen ] = useState( true );
	const [ redirectAfterSave, setRedirectAfterSave ] = useState( false );
	const [ productDescription, setProductDescription ] = useState( '' );
	const [ coverLetter, setCoverLetter ] = useState( '' );
	const [ shippingCarrier, setShippingCarrier ] = useState( '' );
	const [ shippingDate, setShippingDate ] = useState( '' );
	const [ shippingTrackingNumber, setShippingTrackingNumber ] = useState(
		''
	);
	const [ shippingAddress, setShippingAddress ] = useState( '' );
	const [ isUploading, setIsUploading ] = useState<
		Record< string, boolean >
	>( {} );
	const [ uploadingErrors, setUploadingErrors ] = useState<
		Record< string, string >
	>( {} );
	const [ fileSizes, setFileSizes ] = useState< Record< string, number > >(
		{}
	);
	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
	} = useDispatch( 'core/notices' );

	// --- Data loading ---
	useEffect( () => {
		const fetchDispute = async () => {
			try {
				const d: any = await apiFetch( { path } );
				setDispute( d );
				// fallback to multiple if no product type is set
				setProductType( d.metadata?.__product_type || '' );
				// Load saved product description from evidence
				setProductDescription( d.evidence?.product_description || '' );
				// Load saved shipping details from evidence
				setShippingCarrier( d.evidence?.shipping_carrier || '' );
				setShippingDate( d.evidence?.shipping_date || '' );
				setShippingTrackingNumber(
					d.evidence?.shipping_tracking_number || ''
				);
				setShippingAddress( d.evidence?.shipping_address || '' );
				// Load saved file IDs from evidence
				setEvidence( ( prev: any ) => ( {
					...prev,
					receipt: d.evidence?.receipt || '',
					customer_communication:
						d.evidence?.customer_communication || '',
					customer_signature: d.evidence?.customer_signature || '',
					refund_policy: d.evidence?.refund_policy || '',
					duplicate_charge_documentation:
						d.evidence?.duplicate_charge_documentation || '',
					shipping_documentation:
						d.evidence?.shipping_documentation || '',
					service_documentation:
						d.evidence?.service_documentation || '',
					cancellation_policy: d.evidence?.cancellation_policy || '',
					access_activity_log: d.evidence?.access_activity_log || '',
					uncategorized_file: d.evidence?.uncategorized_file || '',
				} ) );

				// Generate default cover letter
				const merchantName = d?.merchant_name || 'Sellthosejeans';
				const merchantAddress =
					d?.merchant_address ||
					'123 High Street, LONDON, SW1A 1AA, UNITED KINGDOM';
				const merchantEmail = d?.merchant_email || 'paul@gmail.com';
				const merchantPhone = d?.merchant_phone || '+13334445566';
				const today = new Date().toLocaleDateString( undefined, {
					year: 'numeric',
					month: 'long',
					day: 'numeric',
				} );
				const acquiringBank =
					'[Acquiring Bank or Payment Processor Name]';
				const caseNumber = '[Chargeback Case Number]';
				const transactionId = '[Transaction ID]';
				const transactionDate = '[Transaction Date]';
				const customerName = '[Customer Name]';
				const product = '[Product]';
				const orderDate = '[Order Date]';
				const defaultLetter = `${ merchantName }
${ merchantAddress }
${ merchantEmail }
${ merchantPhone }
${ today }

To: ${ acquiringBank }
Subject: Chargeback Dispute – Case # ${ caseNumber }

Dear Dispute Resolution Team,

We are submitting evidence in response to chargeback # ${ caseNumber } for transaction # ${ transactionId } on ${ transactionDate }.

Our records indicate that the customer and legitimate cardholder, ${ customerName }, ordered ${ product } on ${ orderDate }.

To support our case, we are providing the following documentation:
• AVS/CVV Match: Billing address and security code matched (Attachment A)
• IP/Device Data: Location and device info used at purchase (Attachment B)
• Customer Confirmation: Email or chat confirming purchase (Attachment C)
• Usage Data: Login records for the digital goods (Attachment D)

Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.

Thank you,
Paul McCartney
${ merchantName }`;
				setCoverLetter( defaultLetter );
			} catch ( error ) {
				createErrorNotice( String( error ) );
			}
		};
		fetchDispute();
	}, [ path, createErrorNotice ] );

	// --- Step logic ---
	const disputeReason = dispute?.reason;
	const hasShipping = needsShipping( disputeReason );
	const panelHeadings = hasShipping
		? [ 'General evidence', 'Shipping information', 'Review' ]
		: [ 'General evidence', 'Review' ];

	useEffect( () => {
		setIsAccordionOpen( currentStep === 0 );
	}, [ currentStep ] );

	// --- Read-only logic ---
	const readOnly =
		dispute &&
		dispute.status !== 'needs_response' &&
		dispute.status !== 'warning_needs_response';

	const updateProductType = ( newType: string ) => {
		recordEvent( 'wcpay_dispute_product_selected', { selection: newType } );
		setProductType( newType );
	};

	const updateProductDescription = ( value: string ) => {
		setProductDescription( value );
		setEvidence( ( prev: any ) => ( {
			...prev,
			product_description: value,
		} ) );
	};

	const updateShippingCarrier = ( value: string ) => {
		setShippingCarrier( value );
		setEvidence( ( prev: any ) => ( {
			...prev,
			shipping_carrier: value,
		} ) );
	};

	const updateShippingDate = ( value: string ) => {
		setShippingDate( value );
		setEvidence( ( prev: any ) => ( {
			...prev,
			shipping_date: value,
		} ) );
	};

	const updateShippingTrackingNumber = ( value: string ) => {
		setShippingTrackingNumber( value );
		setEvidence( ( prev: any ) => ( {
			...prev,
			shipping_tracking_number: value,
		} ) );
	};

	const updateShippingAddress = ( value: string ) => {
		setShippingAddress( value );
		setEvidence( ( prev: any ) => ( {
			...prev,
			shipping_address: value,
		} ) );
	};

	// --- File upload logic ---
	const isUploadingEvidence = () =>
		Object.values( isUploading ).some( Boolean );

	const fileSizeExceeded = ( latestFileSize: number ) => {
		const fileSizeLimitInBytes = 4500000;
		const totalFileSize =
			Object.values( fileSizes ).reduce(
				( acc, fileSize ) => acc + fileSize,
				0
			) + latestFileSize;
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
		return false;
	};

	const doUploadFile = async ( key: string, file: File ) => {
		if ( ! file ) return;

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
		setIsUploading( ( prev ) => ( { ...prev, [ key ]: true } ) );
		setUploadingErrors( ( prev ) => ( { ...prev, [ key ]: '' } ) );

		// Force reload evidence components.
		setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );

		try {
			const uploadedFile: any = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );

			// Store uploaded file name in metadata to display in submitted evidence or saved for later form.
			setEvidence( ( e: any ) => ( { ...e, [ key ]: uploadedFile.id } ) );
			setFileSizes( ( prev ) => ( {
				...prev,
				[ key ]: uploadedFile.size,
			} ) );

			recordEvent( 'wcpay_dispute_file_upload_success', {
				type: key,
			} );
		} catch ( err ) {
			recordEvent( 'wcpay_dispute_file_upload_failed', {
				message: err instanceof Error ? err.message : String( err ),
			} );

			setUploadingErrors( ( prev ) => ( {
				...prev,
				[ key ]: err instanceof Error ? err.message : String( err ),
			} ) );

			// Force reload evidence components.
			setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );
		} finally {
			setIsUploading( ( prev ) => ( { ...prev, [ key ]: false } ) );
		}
	};

	const doRemoveFile = ( key: string ) => {
		setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );
		setUploadingErrors( ( prev ) => ( { ...prev, [ key ]: '' } ) );
		setFileSizes( ( prev ) => ( { ...prev, [ key ]: 0 } ) );
	};

	// --- Navigation warning ---
	const pristine = useMemo(
		() =>
			JSON.stringify( evidence ) ===
			JSON.stringify( dispute?.evidence || {} ),
		[ evidence, dispute ]
	);
	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( pristine || redirectAfterSave ) return;
		return __(
			'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
			'woocommerce-payments'
		);
	} );
	useEffect( () => {
		confirmationNavigationCallback();
	}, [ pristine, confirmationNavigationCallback, redirectAfterSave ] );

	// --- Save/submit logic ---
	const doSave = async ( submit: boolean ) => {
		// Prevent submit if upload is in progress
		if ( isUploadingEvidence() ) {
			createInfoNotice(
				__(
					'Please wait until file upload is finished',
					'woocommerce-payments'
				)
			);
			return;
		}

		try {
			recordEvent(
				submit
					? 'wcpay_dispute_submit_evidence_clicked'
					: 'wcpay_dispute_save_evidence_clicked'
			);

			createSuccessNotice(
				submit
					? __( 'Evidence submitted!', 'woocommerce-payments' )
					: __( 'Evidence saved!', 'woocommerce-payments' ),
				{
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
				}
			);

			// Only include file keys in the evidence object if they have a non-empty value
			const evidenceToSend = Object.fromEntries(
				Object.entries( {
					...dispute.evidence,
					product_description: productDescription,
					receipt: evidence.receipt,
					customer_communication: evidence.customer_communication,
					customer_signature: evidence.customer_signature,
					refund_policy: evidence.refund_policy,
					duplicate_charge_documentation:
						evidence.duplicate_charge_documentation,
					shipping_documentation: evidence.shipping_documentation,
					service_documentation: evidence.service_documentation,
					cancellation_policy: evidence.cancellation_policy,
					access_activity_log: evidence.access_activity_log,
					uncategorized_file: evidence.uncategorized_file,
					// Add shipping details
					shipping_carrier: shippingCarrier,
					shipping_date: shippingDate,
					shipping_tracking_number: shippingTrackingNumber,
					shipping_address: shippingAddress,
				} ).filter( ( [ value ] ) => value && value !== '' )
			);

			// Update metadata with the current productType
			const updatedMetadata = {
				...dispute.metadata,
				__product_type: productType,
			};

			await apiFetch( {
				path,
				method: 'post',
				data: {
					evidence: evidenceToSend,
					metadata: updatedMetadata,
					submit,
				},
			} );

			recordEvent(
				submit
					? 'wcpay_dispute_submit_evidence_success'
					: 'wcpay_dispute_save_evidence_success'
			);

			setRedirectAfterSave( true );
		} catch ( err ) {
			recordEvent(
				submit
					? 'wcpay_dispute_submit_evidence_failed'
					: 'wcpay_dispute_save_evidence_failed'
			);

			const message = submit
				? __(
						'Failed to submit evidence. (%s)',
						'woocommerce-payments'
				  )
				: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
			createErrorNotice(
				sprintf(
					message,
					err instanceof Error ? err.message : String( err )
				)
			);
		}
	};

	// --- Accordion summary content ---
	const summaryItems = useMemo( () => {
		if ( ! dispute ) return [];
		const disputeReasonSummary = reasons[ dispute.reason ]?.summary || [];
		return [
			{
				title: __( 'Dispute Amount', 'woocommerce-payments' ),
				content: formatExplicitCurrency(
					dispute.amount,
					dispute.currency
				),
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
						{ reasons[ dispute.reason ]?.display || dispute.reason }
						{ disputeReasonSummary.length > 0 && (
							<ClickTooltip
								buttonLabel={ __(
									'Learn more',
									'woocommerce-payments'
								) }
								content={
									<div className="dispute-reason-tooltip">
										<p>
											{ reasons[ dispute.reason ]
												?.display || dispute.reason }
										</p>
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
					<DisputeDueByDate
						dueBy={ dispute.evidence_details?.due_by }
					/>
				),
			},
			{
				title: __( 'Order', 'woocommerce-payments' ),
				content: <OrderLink order={ dispute.order } />,
			},
		];
	}, [ dispute ] );

	// --- Recommended documents ---
	const recommendedDocumentFields = [
		{
			key: 'receipt',
			label: __( 'Order receipt', 'woocommerce-payments' ),
		},
		{
			key: 'customer_communication',
			label: __( 'Customer communication', 'woocommerce-payments' ),
		},
		{
			key: 'customer_signature',
			label: __( 'Customer signature', 'woocommerce-payments' ),
		},
		{
			key: 'refund_policy',
			label: __(
				'Copy of the store refund policy',
				'woocommerce-payments'
			),
		},
		{
			key: 'uncategorized_file',
			label: __(
				'Any additional documents you think will support the case',
				'woocommerce-payments'
			),
		},
	];

	// --- Recommended shipping documents ---
	const recommendedShippingDocumentFields = [
		{
			key: 'shipping_documentation',
			label: __( 'Proof of shipping', 'woocommerce-payments' ),
		},
	];

	const recommendedDocumentsFields = recommendedDocumentFields.map(
		( field ) => ( {
			key: field.key,
			label: field.label,
			fileName: evidence[ field.key ] || '',
			uploaded: !! evidence[ field.key ],
			isLoading: isUploading[ field.key ] || false,
			error: uploadingErrors[ field.key ] || '',
			onFileChange: ( key: string, file: File ) =>
				Promise.resolve( doUploadFile( field.key, file ) ),
			onFileRemove: () => Promise.resolve( doRemoveFile( field.key ) ),
			isBusy: isUploading[ field.key ] || false,
		} )
	);

	const recommendedShippingDocumentsFields = recommendedShippingDocumentFields.map(
		( field ) => ( {
			key: field.key,
			label: field.label,
			fileName: evidence[ field.key ] || '',
			uploaded: !! evidence[ field.key ],
			isLoading: isUploading[ field.key ] || false,
			error: uploadingErrors[ field.key ] || '',
			onFileChange: ( key: string, file: File ) =>
				Promise.resolve( doUploadFile( field.key, file ) ),
			onFileRemove: () => Promise.resolve( doRemoveFile( field.key ) ),
			isBusy: isUploading[ field.key ] || false,
		} )
	);

	const bankName = dispute?.charge ? getBankName( dispute.charge ) : null;

	const inlineNotice = ( bankNameValue: string | null ) => (
		<InlineNotice
			icon
			isDismissible={ false }
			status="info"
			className="dispute-steps__notice-content"
		>
			{ createInterpolateElement(
				bankNameValue
					? sprintf(
							__(
								'<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. <strong>%1$s</strong> makes the decision in this process.',
								'woocommerce-payments'
							),
							bankNameValue
					  )
					: __(
							"<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. The cardholder's bank makes the decision in this process.",
							'woocommerce-payments'
					  ),
				{
					strong: <strong />,
				}
			) }
		</InlineNotice>
	);

	// --- Step content ---
	const renderStepContent = () => {
		// if ( ! fields.length ) return null;
		if ( currentStep === 0 ) {
			return (
				<>
					<h2 className="wcpay-dispute-evidence-new__stepper-title">
						{ steps[ 0 ].heading }
					</h2>
					<p className="wcpay-dispute-evidence-new__stepper-subheading">
						{ steps[ 0 ].subheading }
					</p>
					<CustomerDetails dispute={ dispute } />
					<ProductDetails
						productType={ productType }
						onProductTypeChange={ updateProductType }
						productDescription={ productDescription }
						onProductDescriptionChange={ updateProductDescription }
						readOnly={ readOnly }
					/>
					<RecommendedDocuments
						fields={ recommendedDocumentsFields }
					/>
					{ inlineNotice( bankName ) }
				</>
			);
		}
		if ( hasShipping && currentStep === 1 ) {
			return (
				<>
					<h2 className="wcpay-dispute-evidence-new__stepper-title">
						{ steps[ 1 ].heading }
					</h2>
					<p className="wcpay-dispute-evidence-new__stepper-subheading">
						{ steps[ 1 ].subheading }
					</p>
					<ShippingDetails
						shippingCarrier={ shippingCarrier }
						shippingDate={ shippingDate }
						shippingTrackingNumber={ shippingTrackingNumber }
						shippingAddress={ shippingAddress }
						readOnly={ readOnly }
						onShippingCarrierChange={ updateShippingCarrier }
						onShippingDateChange={ updateShippingDate }
						onShippingTrackingNumberChange={
							updateShippingTrackingNumber
						}
						onShippingAddressChange={ updateShippingAddress }
					/>
					<RecommendedDocuments
						fields={ recommendedShippingDocumentsFields }
					/>
					{ inlineNotice( bankName ) }
				</>
			);
		}
		// Review step
		const reviewStep = hasShipping ? 2 : 1;
		if ( currentStep === reviewStep ) {
			return (
				<>
					<h2 className="wcpay-dispute-evidence-new__stepper-title">
						{ steps[ reviewStep ].heading }
					</h2>
					<p className="wcpay-dispute-evidence-new__stepper-subheading">
						{ steps[ reviewStep ].subheading }
					</p>
					<CoverLetter
						value={ coverLetter }
						onChange={ setCoverLetter }
					/>
					{ inlineNotice( bankName ) }
				</>
			);
		}
		return null;
	};

	// --- Button rendering ---
	const renderButtons = () => {
		const reviewStep = hasShipping ? 2 : 1;
		if ( currentStep === 0 ) {
			return (
				<div className="wcpay-dispute-evidence-new__button-row">
					<Button
						variant="secondary"
						onClick={ () =>
							( window.location.href = getAdminUrl( {
								page: 'wc-admin',
								path: '/payments/disputes/details',
								id: dispute?.id,
							} ) )
						}
					>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<div className="wcpay-dispute-evidence-new__button-group-right">
						<Button
							variant="tertiary"
							onClick={ () => doSave( false ) }
						>
							{ __( 'Save for later', 'woocommerce-payments' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => setCurrentStep( ( s ) => s + 1 ) }
						>
							{ __( 'Next', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			);
		}
		if ( currentStep < reviewStep ) {
			return (
				<div className="wcpay-dispute-evidence-new__button-row">
					<Button
						variant="secondary"
						onClick={ () => setCurrentStep( ( s ) => s - 1 ) }
					>
						{ __( 'Back', 'woocommerce-payments' ) }
					</Button>
					<div className="wcpay-dispute-evidence-new__button-group-right">
						<Button
							variant="tertiary"
							onClick={ () => doSave( false ) }
						>
							{ __( 'Save for later', 'woocommerce-payments' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => setCurrentStep( ( s ) => s + 1 ) }
						>
							{ __( 'Next', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			);
		}
		return (
			<div className="wcpay-dispute-evidence-new__button-row">
				<Button
					variant="secondary"
					onClick={ () => setCurrentStep( ( s ) => s - 1 ) }
				>
					{ __( 'Back', 'woocommerce-payments' ) }
				</Button>
				<div className="wcpay-dispute-evidence-new__button-group-right">
					<Button
						variant="tertiary"
						onClick={ () => doSave( false ) }
					>
						{ __( 'Save for later', 'woocommerce-payments' ) }
					</Button>
					<Button variant="primary" onClick={ () => doSave( true ) }>
						{ __( 'Submit', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		);
	};

	// --- Main render ---
	return (
		<Page maxWidth={ 1032 } className="wcpay-dispute-evidence">
			<TestModeNotice currentPage="disputes" isDetailsView={ true } />
			<ErrorBoundary>
				<div className="wcpay-dispute-evidence-new">
					{ /* Section 1: Accordion */ }
					<Accordion highDensity>
						<AccordionBody
							title="Challenge dispute"
							opened={ isAccordionOpen }
							onToggle={ setIsAccordionOpen }
						>
							<AccordionRow>
								<div className="evidence-summary__body">
									{ dispute && (
										<DisputeNotice
											dispute={ dispute }
											isUrgent={
												dispute.evidence_details
													?.due_by <
												Date.now() / 1000
											}
											paymentMethod={
												dispute.payment_method_details
													?.type || null
											}
											bankName={ bankName }
										/>
									) }
									<HorizontalList items={ summaryItems } />
								</div>
							</AccordionRow>
						</AccordionBody>
					</Accordion>
					{ /* Section 2: Stepper */ }
					<div className="wcpay-dispute-evidence-new__stepper-section">
						<StepperPanel
							steps={ panelHeadings }
							currentStep={ currentStep }
						/>
						<HorizontalRule className="wcpay-dispute-evidence-new__stepper-divider" />
						<div className="wcpay-dispute-evidence-new__stepper-content">
							{ renderStepContent() }
							{ renderButtons() }
						</div>
					</div>
				</div>
			</ErrorBoundary>
		</Page>
	);
};
