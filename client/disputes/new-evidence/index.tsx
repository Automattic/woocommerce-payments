/** @format **/

/**
 * External dependencies
 */
import React, { useState, useEffect, useMemo } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import { recordEvent } from 'tracks';
import { FileUploadControl, UploadedReadOnly } from 'components/file-upload';
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
import evidenceFields, { DisputeReason, ProductType } from './fields';
import CustomerDetails from './customer-details';
import ProductDetails from './product-details';
import RecommendedDocuments from './recommended-documents';
import InlineNotice from 'components/inline-notice';

/**
 * Internal dependencies.
 */
import {
	Button,
	HorizontalRule,
	SelectControl,
} from 'wcpay/components/wp-components-wrapped';
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
	const [ loading, setLoading ] = useState( false );
	const [ evidence, setEvidence ] = useState< any >( {} );
	const [ productType, setProductType ] = useState< string >( '' );
	const [ currentStep, setCurrentStep ] = useState( 0 );
	const [ isAccordionOpen, setIsAccordionOpen ] = useState( true );
	const [ redirectAfterSave, setRedirectAfterSave ] = useState( false );
	const [ productDescription, setProductDescription ] = useState('');
	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
	} = useDispatch( 'core/notices' );

	// --- Data loading ---
	useEffect( () => {
		const fetchDispute = async () => {
			setLoading( true );
			try {
				const d: any = await apiFetch( { path } );
				setDispute( d );
				// fallback to multiple if no product type is set
				setProductType( d.metadata?.__product_type || 'multiple' );
			} finally {
				setLoading( false );
			}
		};
		fetchDispute();
	}, [ path ] );

	// --- Step logic ---
	const disputeReason = dispute?.reason;
	const hasShipping = needsShipping( disputeReason );
	const panelHeadings = hasShipping
		? [ 'General evidence', 'Shipping information', 'Review' ]
		: [ 'General evidence', 'Review' ];

	useEffect( () => {
		setIsAccordionOpen( currentStep === 0 );
	}, [ currentStep ] );

	// --- Evidence fields ---
	const fields = useMemo(
		() =>
			evidenceFields(
				disputeReason as DisputeReason,
				productType as ProductType
			),
		[ disputeReason, productType ]
	);

	// --- Read-only logic ---
	const readOnly =
		dispute &&
		dispute.status !== 'needs_response' &&
		dispute.status !== 'warning_needs_response';

	// --- Notices for error deduplication ---
	const { getNotices } = useSelect(
		( select: any ) => select( 'core/notices' ),
		[]
	);

	// --- Evidence update helpers ---
	const isEvidenceWithinLengthLimit = ( field: any, value: string ) => {
		if ( field.maxLength && value.length >= field.maxLength ) {
			return false;
		}
		// Optionally, add total length check here if needed
		return true;
	};
	const updateEvidence = ( key: string, value: any, field?: any ) => {
		if ( field && ! isEvidenceWithinLengthLimit( field, value ) ) {
			const errorMessage = __(
				'Reached maximum character count for evidence',
				'woocommerce-payments'
			);
			if (
				! getNotices().some(
					( notice: any ) => notice.content === errorMessage
				)
			) {
				createErrorNotice( errorMessage );
			}
			return;
		}
		setEvidence( ( e: any ) => ( { ...e, [ key ]: value } ) );
	};
	const updateProductType = ( newType: string ) => {
		recordEvent( 'wcpay_dispute_product_selected', { selection: newType } );
		setProductType( newType );
	};

	// --- File upload logic ---
	const doUploadFile = async ( key: string, file: File ) => {
		if ( ! file ) return;
		// TODO: Add file size checks, error handling, etc.
		try {
			const body = new FormData();
			body.append( 'file', file );
			body.append( 'purpose', 'dispute_evidence' );
			const uploadedFile: any = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );
			setEvidence( ( e: any ) => ( { ...e, [ key ]: uploadedFile.id } ) );
		} catch ( err ) {
			if (err instanceof Error) {
				createErrorNotice(err.message);
			} else {
				createErrorNotice(String(err));
			}
		}
	};
	const doRemoveFile = ( key: string ) =>
		setEvidence( ( e: any ) => ( { ...e, [ key ]: '' } ) );

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
		setLoading( true );
		try {
			await apiFetch( {
				path,
				method: 'post',
				data: {
					evidence: { ...dispute.evidence, ...evidence },
					metadata: dispute.metadata,
					submit,
				},
			} );
			createSuccessNotice(
				submit
					? __( 'Evidence submitted!', 'woocommerce-payments' )
					: __( 'Evidence saved!', 'woocommerce-payments' )
			);
			setRedirectAfterSave( true );
		} catch ( err ) {
			if (err instanceof Error) {
				createErrorNotice(err.message);
			} else {
				createErrorNotice(String(err));
			}
		} finally {
			setLoading( false );
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
			key: 'order_receipt',
			label: __('Order receipt', 'woocommerce-payments'),
		},
		{
			key: 'customer_communication',
			label: __('Customer communication', 'woocommerce-payments'),
		},
		{
			key: 'customer_signature',
			label: __('Customer signature', 'woocommerce-payments'),
		},
		{
			key: 'refund_policy',
			label: __('Copy of the store refund policy', 'woocommerce-payments'),
		},
		{
			key: 'additional_documents',
			label: __('Any additional documents you think will support the case', 'woocommerce-payments'),
		},
	];

	const recommendedDocumentsFields = recommendedDocumentFields.map((field) => ({
		key: field.key,
		label: field.label,
		fileName: evidence[field.key] || '',
		uploaded: !!evidence[field.key],
		onFileChange: (_key: string, file: File) => Promise.resolve(doUploadFile(field.key, file)),
		onFileRemove: () => Promise.resolve(doRemoveFile(field.key)),
	}));

	const bankName = dispute?.charge ? getBankName( dispute.charge ) : null;

	// --- Step content ---
	const renderStepContent = () => {
		if ( ! fields.length ) return null;
		// Helper to render a field with readOnly logic
		const renderField = ( field: any ) => {
			if ( field.type === 'file' ) {
				return readOnly ? (
					<UploadedReadOnly
						key={ field.key }
						field={ field }
						fileName={ evidence[ field.key ] || '' }
						showPreview={ false }
						accept={ '.pdf, image/png, image/jpeg' }
						isDone={ !! evidence[ field.key ] }
						isLoading={ false }
						error={ '' }
						disabled={ true }
						onFileChange={ async () => {} }
						onFileRemove={ () => {} }
					/>
				) : (
					<FileUploadControl
						key={ field.key }
						field={ field }
						fileName={ evidence[ field.key ] || '' }
						onFileChange={ ( _key: string, file: File ) =>
							doUploadFile( field.key, file )
						}
						onFileRemove={ field.onFileRemove }
						accept={ '.pdf, image/png, image/jpeg' }
						isDone={ !! evidence[ field.key ] }
						isLoading={ false }
						error={ '' }
						disabled={ false }
					/>
				);
			}
			if ( field.type === 'text' ) {
				return (
					<input
						key={ field.key }
						type="text"
						value={ evidence[ field.key ] || '' }
						onChange={ ( e: any ) =>
							updateEvidence( field.key, e.target.value, field )
						}
						className="components-text-control__input"
						disabled={ readOnly }
					/>
				);
			}
			if ( field.type === 'date' ) {
				return (
					<input
						key={ field.key }
						type="date"
						value={ evidence[ field.key ] || '' }
						onChange={ ( e: any ) =>
							updateEvidence( field.key, e.target.value, field )
						}
						className="components-text-control__input"
						disabled={ readOnly }
					/>
				);
			}
			// textarea
			return (
				<textarea
					key={ field.key }
					value={ evidence[ field.key ] || '' }
					onChange={ ( e: any ) =>
						updateEvidence( field.key, e.target.value, field )
					}
					maxLength={ field.maxLength }
					className="components-textarea-control__input"
					disabled={ readOnly }
				/>
			);
		};
		if ( currentStep === 0 ) {
			return (
				<>
					<h2 className="wcpay-dispute-evidence-new__stepper-title">
						{ steps[ 0 ].heading }
					</h2>
					<p className="wcpay-dispute-evidence-new__stepper-subheading">
						{ steps[ 0 ].subheading }
					</p>
					<CustomerDetails dispute={dispute} />
					<ProductDetails
						productType={productType}
						onProductTypeChange={updateProductType}
						productDescription={productDescription}
						onProductDescriptionChange={setProductDescription}
						readOnly={readOnly}
					/>
					<RecommendedDocuments fields={recommendedDocumentsFields} />
					<InlineNotice
						icon
						isDismissible={ false }
						status="info"
						className="dispute-steps__notice-content"
					>
						{ createInterpolateElement(
							bankName
								? sprintf(
										__(
											'<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. <strong>%1$s</strong> makes the decision in this process.',
											'woocommerce-payments'
										),
										bankName
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
					{ fields
						.filter(
							( s: any ) => s.key === 'shipping_information'
						)
						.map( ( section: any ) => (
							<div key={ section.key }>
								{ section.fields.map( renderField ) }
							</div>
						) ) }
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
