/** @format **/

/**
 * External dependencies
 */
import React, { useState, useEffect, useMemo, useRef } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { chevronLeft, chevronRight } from '@wordpress/icons';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies.
 */
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
import {
	generateCoverLetter,
	getBusinessDetails,
} from './cover-letter-generator';
import { useGetSettings, useDisputeEvidence } from 'wcpay/data';
import CustomerDetails from './customer-details';
import ProductDetails from './product-details';
import RecommendedDocuments from './recommended-documents';
import InlineNotice from 'components/inline-notice';
import ShippingDetails from './shipping-details';
import CoverLetter from './cover-letter';
import { Button, HorizontalRule } from 'wcpay/components/wp-components-wrapped';
import { getAdminUrl } from 'wcpay/utils';
import { StepperPanel } from 'wcpay/components/stepper';
import {
	Accordion,
	AccordionBody,
	AccordionRow,
} from 'wcpay/components/accordion';
import Page from 'wcpay/components/page';
import { createInterpolateElement } from '@wordpress/element';
import {
	DOCUMENT_FIELD_KEYS,
	getRecommendedDocumentFields,
	getRecommendedShippingDocumentFields,
} from './recommended-document-fields';
import { RecommendedDocument } from './types';

import './style.scss';
import RefundStatus from './refund-status';
import DuplicateStatus from './duplicate-status';

// --- Utility: Determine if shipping is required for a given reason ---
const ReasonsNeedShipping = [
	'product_unacceptable',
	'product_not_received',
	'general',
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
			"The more info you can provide, the stronger your case will be. To speed things up, we've prefilled some fields for you — please check for accuracy and upload any relevant documents.",
	},
	{
		heading: 'Add your shipping details',
		subheading:
			"We've prefilled some of this for you — please check that it's correct and upload the recommended document.",
	},
	{
		heading: 'Review your cover letter',
		subheading:
			"Using the information you've provided, we've automatically generated a cover letter for you. Before submitting to your customer's bank, please check all of the details are correct and make any required changes.",
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
	const [
		isCoverLetterManuallyEdited,
		setIsCoverLetterManuallyEdited,
	] = useState( false );
	const [ shippingCarrier, setShippingCarrier ] = useState( '' );
	const [ shippingDate, setShippingDate ] = useState( '' );
	const [ shippingTrackingNumber, setShippingTrackingNumber ] = useState(
		''
	);
	const [ shippingAddress, setShippingAddress ] = useState( '' );
	const [ isUploading, setIsUploading ] = useState<
		Record< string, boolean >
	>( {} );
	const [ fileSizes, setFileSizes ] = useState< Record< string, number > >(
		{}
	);
	// This is used to display the file name in the UI.
	const [ uploadedFiles, setUploadedFiles ] = useState<
		Record< string, string >
	>( {} );
	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
	} = useDispatch( 'core/notices' );
	const { updateDispute: updateDisputeInStore } = useDisputeEvidence();
	const settings = useGetSettings();
	const bankName = dispute?.charge ? getBankName( dispute.charge ) : null;
	const [ refundStatus, setRefundStatus ] = useState(
		'refund_has_been_issued'
	);
	const [ duplicateStatus, setDuplicateStatus ] = useState( 'is_duplicate' );

	// Refs for heading elements to focus on step navigation
	const stepHeadingRefs = useRef< {
		[ key: number ]: HTMLHeadingElement | null;
	} >( {} );

	// --- Data loading ---
	useEffect( () => {
		const fetchDispute = async () => {
			try {
				const d: any = await apiFetch( { path } );
				setDispute( d );
				// fallback to multiple if no product type is set
				setProductType( d.metadata?.__product_type || '' );
				// Load saved product description from evidence or level3 line items
				const level3ProductNames = d.charge?.level3?.line_items
					?.map( ( item: any ) => item.product_description )
					.filter( Boolean )
					.join( ', ' );
				setProductDescription(
					d.evidence?.product_description || level3ProductNames || ''
				);
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

				// Set cover letter from saved evidence or generate new one
				const savedCoverLetter = d.evidence?.uncategorized_text;
				if ( savedCoverLetter ) {
					setCoverLetter( savedCoverLetter );
					// Create a dispute object with current evidence state for comparison
					const disputeWithCurrentEvidence = {
						...d,
						evidence: {
							...d.evidence,
							product_description:
								d.evidence?.product_description || '',
							receipt: d.evidence?.receipt || '',
							customer_communication:
								d.evidence?.customer_communication || '',
							customer_signature:
								d.evidence?.customer_signature || '',
							refund_policy: d.evidence?.refund_policy || '',
							duplicate_charge_documentation:
								d.evidence?.duplicate_charge_documentation ||
								'',
							shipping_documentation:
								d.evidence?.shipping_documentation || '',
							service_documentation:
								d.evidence?.service_documentation || '',
							cancellation_policy:
								d.evidence?.cancellation_policy || '',
							access_activity_log:
								d.evidence?.access_activity_log || '',
							uncategorized_file:
								d.evidence?.uncategorized_file || '',
							shipping_carrier:
								d.evidence?.shipping_carrier || '',
							shipping_date: d.evidence?.shipping_date || '',
							shipping_tracking_number:
								d.evidence?.shipping_tracking_number || '',
							shipping_address:
								d.evidence?.shipping_address || '',
						},
					};
					// Only mark as manually edited if it differs from what would be auto-generated
					const generatedContent = generateCoverLetter(
						disputeWithCurrentEvidence,
						getBusinessDetails(),
						settings,
						bankName,
						refundStatus,
						duplicateStatus
					);
					setIsCoverLetterManuallyEdited(
						savedCoverLetter !== generatedContent
					);
				} else {
					// Generate new cover letter
					const generatedCoverLetter = generateCoverLetter(
						d,
						getBusinessDetails(),
						settings,
						bankName,
						refundStatus,
						duplicateStatus
					);
					setCoverLetter( generatedCoverLetter );
					setIsCoverLetterManuallyEdited( false );
				}
			} catch ( error ) {
				createErrorNotice( String( error ) );
			}
		};
		fetchDispute();
	}, [
		path,
		createErrorNotice,
		settings,
		bankName,
		refundStatus,
		duplicateStatus,
	] );

	// --- File name display logic ---
	useEffect( () => {
		const fetchFile = async () => {
			const allFileKeys = Object.values( DOCUMENT_FIELD_KEYS );
			// Filter out the file keys that are not in the dispute evidence.
			const fileKeys = allFileKeys.filter(
				( fileKey ) => dispute?.evidence?.[ fileKey ]
			);
			// If we don't have any file keys, return.
			if ( fileKeys.length === 0 ) return;
			// If we already loaded the file details, return.
			if ( Object.keys( uploadedFiles ).length > 0 ) return;
			// Build the URLS to bulk fetch the file details.
			const fileDetails = await Promise.all(
				fileKeys.map( async ( fileKey ) => {
					const fileId = dispute?.evidence?.[ fileKey ];
					if ( ! fileId ) return null;
					const file: any = await apiFetch( {
						path: `/wc/v3/payments/file/${ fileId }/details`,
					} );
					return {
						fileKey: fileKey,
						filename: file.filename,
						size: file.size,
					};
				} )
			);
			const filteredFileDetails = fileDetails.filter(
				( fileDetail ) => fileDetail !== null
			);
			setUploadedFiles( ( prev ) => ( {
				...prev,
				...Object.fromEntries(
					filteredFileDetails.map( ( fileDetail ) => [
						fileDetail?.fileKey,
						fileDetail?.filename,
					] )
				),
			} ) );
			// Also set the file sizes
			setFileSizes( ( prev ) => ( {
				...prev,
				...Object.fromEntries(
					filteredFileDetails.map( ( fileDetail ) => [
						fileDetail?.fileKey,
						fileDetail?.size,
					] )
				),
			} ) );
		};
		fetchFile();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- We only want to fetch the file details when uploadedFiles changes.
	}, [ dispute?.evidence ] );

	// Update cover letter when evidence changes
	useEffect( () => {
		if ( ! dispute || ! settings ) return;

		// Create a dispute object with current evidence state for generation
		const disputeWithCurrentEvidence = {
			...dispute,
			evidence: {
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
				shipping_carrier: shippingCarrier,
				shipping_date: shippingDate,
				shipping_tracking_number: shippingTrackingNumber,
				shipping_address: shippingAddress,
			},
		};

		const generatedCoverLetter = generateCoverLetter(
			disputeWithCurrentEvidence,
			getBusinessDetails(),
			settings,
			bankName,
			refundStatus,
			duplicateStatus
		);

		// Only auto-update if not manually edited, or if the current content matches what was previously generated
		if (
			! isCoverLetterManuallyEdited ||
			coverLetter === generatedCoverLetter
		) {
			setCoverLetter( generatedCoverLetter );
			setIsCoverLetterManuallyEdited( false );
		}
	}, [
		dispute,
		settings,
		bankName,
		isCoverLetterManuallyEdited,
		evidence,
		productDescription,
		shippingCarrier,
		shippingDate,
		shippingTrackingNumber,
		shippingAddress,
		refundStatus,
		duplicateStatus,
		coverLetter,
	] );

	// --- Step logic ---
	const disputeReason = dispute?.reason;
	const hasShipping = needsShipping( disputeReason );
	const panelHeadings = hasShipping
		? [ 'Purchase info', 'Shipping details', 'Review' ]
		: [ 'Purchase info', 'Review' ];

	useEffect( () => {
		setIsAccordionOpen( currentStep === 0 );
	}, [ currentStep ] );

	// --- File upload logic ---
	const isUploadingEvidence = () =>
		Object.values( isUploading ).some( Boolean );

	// --- Save/submit logic ---
	const handleSaveSuccess = ( submit: boolean ) => {
		const message = submit
			? __( 'Evidence submitted!', 'woocommerce-payments' )
			: __( 'Evidence saved!', 'woocommerce-payments' );

		recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_success'
				: 'wcpay_dispute_save_evidence_success'
		);

		createSuccessNotice( message, {
			id: submit
				? 'evidence-submitted'
				: `evidence-saved-${ dispute.id }`,
		} );

		// Only redirect after submission, not after save
		if ( submit ) {
			setRedirectAfterSave( true );
		}
	};

	const handleSaveError = ( err: any, submit: boolean ) => {
		recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_failed'
				: 'wcpay_dispute_save_evidence_failed'
		);

		const message = submit
			? __( 'Failed to submit evidence. (%s)', 'woocommerce-payments' )
			: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
		createErrorNotice(
			sprintf(
				message,
				err instanceof Error ? err.message : String( err )
			)
		);
	};

	const doSave = async ( submit: boolean, notify = true ) => {
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
					uncategorized_text: coverLetter,
					// Add shipping details
					shipping_carrier: shippingCarrier,
					shipping_date: shippingDate,
					shipping_tracking_number: shippingTrackingNumber,
					shipping_address: shippingAddress,
					customer_purchase_ip: dispute.order?.ip_address,
				} ).filter( ( [ value ] ) => value && value !== '' )
			);

			// Update metadata with the current productType
			const updatedMetadata = {
				...dispute.metadata,
				__product_type: productType,
			};

			const updatedDispute = await apiFetch( {
				path,
				method: 'post',
				data: {
					evidence: evidenceToSend,
					metadata: updatedMetadata,
					submit,
				},
			} );

			setDispute( updatedDispute );
			if ( notify ) {
				handleSaveSuccess( submit );
			}
			updateDisputeInStore( updatedDispute as any );

			if ( submit ) {
				setEvidence( {} );
			}
		} catch ( err ) {
			handleSaveError( err, submit );
		}
	};

	// --- Read-only logic ---
	const readOnly =
		dispute &&
		dispute.status !== 'needs_response' &&
		dispute.status !== 'warning_needs_response';

	// --- Handle step changes ---
	const handleStepChange = async ( newStep: number ) => {
		// Only save if not in readOnly mode
		if ( ! readOnly ) {
			await doSave( false, false );
		}
		// Update step
		setCurrentStep( newStep );
		// Scroll to top of page
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	};

	const handleStepBack = ( step: number ) => {
		setCurrentStep( step );
		// Scroll to top of page
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	};

	// Focus on heading when step changes
	useEffect( () => {
		// Use setTimeout to ensure the DOM has updated with the new step content
		const timeoutId = setTimeout( () => {
			const headingRef = stepHeadingRefs.current[ currentStep ];
			if ( headingRef ) {
				headingRef.focus();
			}
		}, 100 );

		return () => clearTimeout( timeoutId );
	}, [ currentStep ] );

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
			// Store uploaded file name to avoid fetching the file details again.
			setUploadedFiles( ( prev ) => ( {
				...prev,
				[ key ]: uploadedFile.filename,
			} ) );
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

			// Display error as WordPress admin notice
			createErrorNotice(
				sprintf(
					__( 'Failed to upload file. (%s)', 'woocommerce-payments' ),
					err instanceof Error ? err.message : String( err )
				)
			);

			// Force reload evidence components.
			setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );
		} finally {
			setIsUploading( ( prev ) => ( { ...prev, [ key ]: false } ) );
		}
	};

	const doRemoveFile = ( key: string ) => {
		setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );
		setFileSizes( ( prev ) => ( { ...prev, [ key ]: 0 } ) );
		// Remove the file name from the uploaded files.
		setUploadedFiles( ( prev ) => ( { ...prev, [ key ]: '' } ) );
	};

	// --- Navigation warning ---
	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( redirectAfterSave || readOnly ) return;
		return __(
			'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
			'woocommerce-payments'
		);
	} );

	// Store the cleanup function from the navigation confirmation
	const [ navigationCleanup, setNavigationCleanup ] = useState<
		( () => void ) | null
	>( null );

	useEffect( () => {
		const cleanup = confirmationNavigationCallback();
		setNavigationCleanup( cleanup );
	}, [ confirmationNavigationCallback, redirectAfterSave, readOnly ] );

	// Redirect after successful submission only
	useEffect( () => {
		if ( redirectAfterSave ) {
			// Clean up navigation confirmation before redirecting
			if ( navigationCleanup ) {
				navigationCleanup();
			}

			const href = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes/details',
				id: dispute?.id,
			} );
			window.location.replace( href );
		}
	}, [ redirectAfterSave, navigationCleanup, dispute?.id ] );

	// --- Accordion summary content ---
	const summaryItems = useMemo( () => {
		if ( ! dispute ) return [];
		const disputeReasonSummary = reasons[ disputeReason ]?.summary || [];
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
							includeTime: false,
					  } )
					: '–',
			},
			{
				title: __( 'Reason', 'woocommerce-payments' ),
				content: (
					<>
						{ reasons[ disputeReason ]?.display || disputeReason }
						{ disputeReasonSummary.length > 0 && (
							<ClickTooltip
								buttonIcon={ <HelpOutlineIcon /> }
								buttonLabel={ __(
									'Learn more',
									'woocommerce-payments'
								) }
								content={
									<div className="dispute-reason-tooltip">
										<p>
											{ reasons[ disputeReason ]
												?.display || disputeReason }
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
	}, [ dispute, disputeReason ] );

	// --- Recommended documents ---
	const recommendedDocumentFields = getRecommendedDocumentFields(
		disputeReason,
		disputeReason === 'credit_not_processed' ? refundStatus : undefined,
		disputeReason === 'duplicate' ? duplicateStatus : undefined
	);

	const recommendedShippingDocumentFields = getRecommendedShippingDocumentFields();
	const recommendedDocumentsFields = recommendedDocumentFields.map(
		( field: RecommendedDocument ) => ( {
			key: field.key,
			label: field.label,
			description: field.description,
			fileName: uploadedFiles[ field.key ] || evidence[ field.key ] || '',
			fileSize: fileSizes[ field.key ] || 0,
			uploaded: !! evidence[ field.key ],
			isLoading: isUploading[ field.key ] || false,
			onFileChange: ( key: string, file: File ) =>
				readOnly
					? Promise.resolve()
					: Promise.resolve( doUploadFile( field.key, file ) ),
			onFileRemove: () =>
				readOnly
					? Promise.resolve()
					: Promise.resolve( doRemoveFile( field.key ) ),
			isBusy: isUploading[ field.key ] || false,
			readOnly: readOnly,
		} )
	);

	const recommendedShippingDocumentsFields = recommendedShippingDocumentFields.map(
		( field: RecommendedDocument ) => ( {
			key: field.key,
			label: field.label,
			description: field.description,
			fileName: uploadedFiles[ field.key ] || evidence[ field.key ] || '',
			fileSize: fileSizes[ field.key ] || 0,
			uploaded: !! evidence[ field.key ],
			isLoading: isUploading[ field.key ] || false,
			onFileChange: ( key: string, file: File ) =>
				readOnly
					? Promise.resolve()
					: Promise.resolve( doUploadFile( field.key, file ) ),
			onFileRemove: () =>
				readOnly
					? Promise.resolve()
					: Promise.resolve( doRemoveFile( field.key ) ),
			isBusy: isUploading[ field.key ] || false,
			readOnly: readOnly,
		} )
	);

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
					<h2
						className="wcpay-dispute-evidence-new__stepper-title"
						ref={ ( el ) => ( stepHeadingRefs.current[ 0 ] = el ) }
						tabIndex={ -1 }
					>
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
					{ /* only show if the dispute reason is credit_not_processed */ }
					{ disputeReason === 'credit_not_processed' && (
						<RefundStatus
							refundStatus={ refundStatus }
							onRefundStatusChange={
								setRefundStatus as ( value: string ) => void
							}
							readOnly={ readOnly }
						/>
					) }
					{ /* only show if the dispute reason is duplicate */ }
					{ disputeReason === 'duplicate' && (
						<DuplicateStatus
							duplicateStatus={ duplicateStatus }
							onDuplicateStatusChange={
								setDuplicateStatus as ( value: string ) => void
							}
							readOnly={ readOnly }
						/>
					) }
					<RecommendedDocuments
						fields={ recommendedDocumentsFields }
						readOnly={ readOnly }
						hasHelperLink={ true }
					/>
					{ inlineNotice( bankName ) }
				</>
			);
		}
		if ( hasShipping && currentStep === 1 ) {
			return (
				<>
					<h2
						className="wcpay-dispute-evidence-new__stepper-title"
						ref={ ( el ) => ( stepHeadingRefs.current[ 1 ] = el ) }
						tabIndex={ -1 }
					>
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
						readOnly={ readOnly }
						customSubheading={ __(
							'We recommend adding the following document(s) to support your case.',
							'woocommerce-payments'
						) }
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
					<h2
						className="wcpay-dispute-evidence-new__stepper-title"
						ref={ ( el ) =>
							( stepHeadingRefs.current[ reviewStep ] = el )
						}
						tabIndex={ -1 }
					>
						{ steps[ reviewStep ].heading }
					</h2>
					<p className="wcpay-dispute-evidence-new__stepper-subheading">
						{ steps[ reviewStep ].subheading }
					</p>
					{ isCoverLetterManuallyEdited && (
						<InlineNotice
							icon
							isDismissible={ false }
							status="warning"
							className="wcpay-dispute-evidence-new__cover-letter-warning"
						>
							{ __(
								"You've made some manual edits to your cover letter. If you update your evidence again, those changes won't be reflected here automatically — but you can always make further edits yourself.",
								'woocommerce-payments'
							) }
						</InlineNotice>
					) }
					<CoverLetter
						value={ coverLetter }
						onChange={ ( newValue: string ) => {
							if ( readOnly ) {
								return;
							}

							// Create a dispute object with current evidence state for generation
							const disputeWithCurrentEvidence = {
								...dispute,
								evidence: {
									...dispute.evidence,
									product_description: productDescription,
									receipt: evidence.receipt,
									customer_communication:
										evidence.customer_communication,
									customer_signature:
										evidence.customer_signature,
									refund_policy: evidence.refund_policy,
									duplicate_charge_documentation:
										evidence.duplicate_charge_documentation,
									shipping_documentation:
										evidence.shipping_documentation,
									service_documentation:
										evidence.service_documentation,
									cancellation_policy:
										evidence.cancellation_policy,
									access_activity_log:
										evidence.access_activity_log,
									uncategorized_file:
										evidence.uncategorized_file,
									shipping_carrier: shippingCarrier,
									shipping_date: shippingDate,
									shipping_tracking_number: shippingTrackingNumber,
									shipping_address: shippingAddress,
								},
							};

							// If the value is empty, regenerate the content
							if ( newValue.trim() === '' ) {
								const generatedContent = generateCoverLetter(
									disputeWithCurrentEvidence,
									getBusinessDetails(),
									settings,
									bankName,
									refundStatus,
									duplicateStatus
								);
								setCoverLetter( generatedContent );
								setIsCoverLetterManuallyEdited( false );
								return;
							}

							// Compare with what would be auto-generated
							const generatedContent = generateCoverLetter(
								disputeWithCurrentEvidence,
								getBusinessDetails(),
								settings,
								bankName,
								refundStatus,
								duplicateStatus
							);
							setCoverLetter( newValue );
							setIsCoverLetterManuallyEdited(
								newValue !== generatedContent
							);
						} }
						readOnly={ readOnly }
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
						{ ! readOnly && (
							<Button
								variant="tertiary"
								onClick={ () => doSave( false ) }
							>
								{ __(
									'Save for later',
									'woocommerce-payments'
								) }
							</Button>
						) }
						<Button
							variant="primary"
							onClick={ () =>
								handleStepChange( currentStep + 1 )
							}
							icon={ chevronRight }
							iconPosition="right"
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
						onClick={ () => handleStepBack( currentStep - 1 ) }
						icon={ chevronLeft }
						iconPosition="left"
					>
						{ __( 'Back', 'woocommerce-payments' ) }
					</Button>
					<div className="wcpay-dispute-evidence-new__button-group-right">
						{ ! readOnly && (
							<Button
								variant="tertiary"
								onClick={ () => doSave( false ) }
							>
								{ __(
									'Save for later',
									'woocommerce-payments'
								) }
							</Button>
						) }
						<Button
							variant="primary"
							icon={ chevronRight }
							iconPosition="right"
							onClick={ () =>
								handleStepChange( currentStep + 1 )
							}
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
					icon={ chevronLeft }
					iconPosition="left"
					onClick={ () => handleStepBack( currentStep - 1 ) }
				>
					{ __( 'Back', 'woocommerce-payments' ) }
				</Button>
				{ ! readOnly && (
					<div className="wcpay-dispute-evidence-new__button-group-right">
						<Button
							variant="tertiary"
							onClick={ () => doSave( false ) }
						>
							{ __( 'Save for later', 'woocommerce-payments' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => doSave( true ) }
						>
							{ __( 'Submit', 'woocommerce-payments' ) }
						</Button>
					</div>
				) }
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
											isUrgent={ true }
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
							onStepClick={ ( stepIndex ) => {
								handleStepChange( stepIndex );
							} }
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
