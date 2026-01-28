/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import type { ExtendedDispute, AccountDetails, CoverLetterData } from './types';
import { DOCUMENT_FIELD_KEYS } from './recommended-document-fields';

// --- Utility Functions ---

export const getBusinessDetails = (): AccountDetails => {
	const wcStoreCountry =
		wcSettings?.admin?.preloadSettings?.general
			?.woocommerce_default_country || ':';
	const [ storeCountry, storeState ] = wcStoreCountry.split( ':' );
	return {
		name: wcSettings?.siteTitle || '<Your Business Name>',
		support_address_city:
			wcSettings?.admin?.preloadSettings?.general
				?.woocommerce_store_city || '',
		support_address_country: storeCountry,
		support_address_line1:
			wcSettings?.admin?.preloadSettings?.general
				?.woocommerce_store_address || '',
		support_address_line2:
			wcSettings?.admin?.preloadSettings?.general
				?.woocommerce_store_address_2 || '',
		support_address_postal_code:
			wcSettings?.admin?.preloadSettings?.general
				?.woocommerce_store_postcode || '',
		support_address_state: storeState,
	};
};

export const formatMerchantAddress = (
	accountDetails: AccountDetails
): string => {
	return `${ accountDetails.support_address_line1 }, ${ accountDetails.support_address_line2 }, ${ accountDetails.support_address_city }, ${ accountDetails.support_address_state } ${ accountDetails.support_address_postal_code } ${ accountDetails.support_address_country }`;
};

export const formatDeliveryDate = (
	dateString: string | undefined
): string => {
	if ( ! dateString )
		return __( '<Delivery/Service Date>', 'woocommerce-payments' );

	const unixTimestamp = Math.floor( new Date( dateString ).getTime() / 1000 );
	return formatDateTimeFromTimestamp( unixTimestamp, {
		separator: ', ',
		includeTime: false,
	} );
};

// --- Attachment Generation ---

const isEvidenceString = (
	evidence: string | Record< string, boolean > | Record< string, string >
): evidence is string => {
	return typeof evidence === 'string';
};

export const generateAttachments = (
	dispute: ExtendedDispute,
	duplicateStatus?: string
): string => {
	const attachments: string[] = [];
	let attachmentCount = 0;

	// Standard attachment definitions with optional restriction rules
	// Each attachment can specify:
	// - `onlyForReasons`: only include for these dispute reasons
	// - `excludeWhen`: exclude when this condition is true (for complex conditions)
	// - `labelForReasons`: use a different label for specific dispute reasons
	// - `labelForStatus`: use a different label based on duplicateStatus
	const standardAttachments: Array< {
		key: string;
		label: string;
		labelForReasons?: { reasons: string[]; label: string };
		labelForStatus?: { status: string; label: string };
		onlyForReasons?: string[];
		excludeWhen?: ( reason: string, status?: string ) => boolean;
	} > = [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
		},
		{
			// For duplicate disputes:
			// - is_duplicate: shows as "Refund receipt" (REFUND_RECEIPT_DOCUMENTATION maps to this)
			// - is_not_duplicate: shows as "Any additional receipts"
			key: DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
			label: __( 'Any additional receipts', 'woocommerce-payments' ),
			onlyForReasons: [ 'duplicate' ],
			labelForStatus: {
				status: 'is_duplicate',
				label: __( 'Refund receipt', 'woocommerce-payments' ),
			},
		},
		{
			// For fraudulent disputes, this shows as "Prior undisputed transaction history"
			// and should appear before Customer communication.
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __(
				'Prior undisputed transaction history',
				'woocommerce-payments'
			),
			onlyForReasons: [ 'fraudulent' ],
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Customer communication', 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			label: __( "Customer's signature", 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Store refund policy', 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
			label: __( 'Proof of shipping', 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Item condition', 'woocommerce-payments' ),
		},
		{
			// For non-fraudulent disputes, "Subscription logs" appears in its original position
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Subscription logs', 'woocommerce-payments' ),
			excludeWhen: ( reason: string ) => reason === 'fraudulent',
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			onlyForReasons: [ 'subscription_canceled' ],
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Cancellation policy', 'woocommerce-payments' ),
			// For subscription_canceled disputes, this field is labeled "Terms of service" in the UI
			labelForReasons: {
				reasons: [ 'subscription_canceled' ],
				label: __( 'Terms of service', 'woocommerce-payments' ),
			},
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Other documents', 'woocommerce-payments' ),
		},
	];

	standardAttachments.forEach(
		( {
			key,
			label,
			labelForReasons,
			labelForStatus,
			onlyForReasons,
			excludeWhen,
		} ) => {
			const evidence = dispute.evidence?.[ key ];

			// Check if this attachment should be skipped based on rules
			if (
				onlyForReasons &&
				! onlyForReasons.includes( dispute.reason )
			) {
				return;
			}
			if ( excludeWhen?.( dispute.reason, duplicateStatus ) ) {
				return;
			}

			if ( evidence && isEvidenceString( evidence ) ) {
				attachmentCount++;
				// Determine the display label with priority:
				// 1. Status-specific label (e.g., "Refund receipt" for is_duplicate)
				// 2. Reason-specific label (e.g., "Terms of service" for subscription_canceled)
				// 3. Default label
				let displayLabel = label;
				if (
					labelForStatus &&
					duplicateStatus === labelForStatus.status
				) {
					displayLabel = labelForStatus.label;
				} else if (
					labelForReasons?.reasons.includes( dispute.reason )
				) {
					displayLabel = labelForReasons.label;
				}
				attachments.push(
					sprintf(
						/* translators: %1$s: label, %2$s: attachment letter */
						__(
							'• %1$s (Attachment %2$s)',
							'woocommerce-payments'
						),
						displayLabel,
						String.fromCharCode( 64 + attachmentCount )
					)
				);
			}
		}
	);

	// If no attachments were provided, use default list
	if ( attachments.length === 0 ) {
		return `${ sprintf(
			/* translators: %s: attachment letter */
			__(
				'• <Attachment description> (Attachment %s)',
				'woocommerce-payments'
			),
			'A'
		) }
${ sprintf(
	/* translators: %s: attachment letter */
	__( '• <Attachment description> (Attachment %s)', 'woocommerce-payments' ),
	'B'
) }`;
	}

	return attachments.join( '\n' );
};

// --- Cover Letter Sections ---

export const generateHeader = ( data: CoverLetterData ): string => {
	return `${ data.merchantName }
${ data.merchantAddress }
${ data.merchantEmail }
${ data.merchantPhone }
${ data.today }`;
};

export const generateRecipient = ( data: CoverLetterData ): string => {
	return `${ sprintf(
		/* translators: %s: acquiring bank name */
		__( 'To: %s', 'woocommerce-payments' ),
		data.acquiringBank
	) }
${ sprintf(
	/* translators: %s: case number */
	__( 'Subject: Chargeback Dispute – Case #%s', 'woocommerce-payments' ),
	data.caseNumber
) }`;
};

const generateBodyUnrecognized = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: product, %3$s: order date */
	__(
		'Our records indicate that the customer and legitimate cardholder, %1$s, ordered %2$s on %3$s.',
		'woocommerce-payments'
	),
	data.customerName,
	data.product,
	data.orderDate
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let me know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const generateBodyCreditNotProcessed = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	if ( data.refundStatus === 'refund_has_been_issued' ) {
		return `${ sprintf(
			/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
			__(
				'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
				'woocommerce-payments'
			),
			data.caseNumber,
			data.transactionId,
			data.transactionDate
		) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: order date, %3$s: refund amount */
	__(
		"Our records indicate that the customer, %1$s, was refunded on %2$s for the amount of %3$s. The refund was processed through our payment provider and should be visible on the customer's statement within 7 - 10 business days.",
		'woocommerce-payments'
	),
	data.customerName,
	data.orderDate,
	dispute.amount
		? `${ ( dispute.amount / 100 ).toFixed(
				2
		  ) } ${ dispute.currency?.toUpperCase() }`
		: __( '[Refund Amount]', 'woocommerce-payments' )
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
	}
	// refund_was_not_owed
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }
${ __(
	'The customer requested a refund outside of the eligible window outlined in our refund policy, which was clearly presented on the website and on the order confirmation.',
	'woocommerce-payments'
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const generateBodyGeneral = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: product, %3$s: order date, %4$s: delivery date */
	__(
		'Our records indicate that the customer, %1$s, ordered %2$s on %3$s and received it on %4$s.',
		'woocommerce-payments'
	),
	data.customerName,
	data.product,
	data.orderDate,
	data.deliveryDate
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const generateBodyProductNotReceived = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: product, %3$s: order date, %4$s: delivery date */
	__(
		'Our records indicate that the customer, %1$s, ordered %2$s on %3$s and received it on %4$s.',
		'woocommerce-payments'
	),
	data.customerName,
	data.product,
	data.orderDate,
	data.deliveryDate
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const generateBodyProductUnacceptable = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: product, %3$s: order date */
	__(
		'Our records indicate that the customer, %1$s, ordered %2$s on %3$s. The product matched the description provided at the time of sale, and we did not receive any indication from the customer that it was defective or not as described.',
		'woocommerce-payments'
	),
	data.customerName,
	data.product,
	data.orderDate
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const generateBodySubscriptionCanceled = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }

${ sprintf(
	/* translators: %1$s: customer name, %2$s: product */
	__(
		"Our records indicate that the customer, %1$s, subscribed to %2$s and was billed according to the terms accepted at the time of signup. The customer's account remained active and no cancellation was recorded prior to the billing date.",
		'woocommerce-payments'
	),
	data.customerName,
	data.product
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

export const generateBodyDuplicate = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	if ( data.duplicateStatus === 'is_duplicate' ) {
		return `${ sprintf(
			/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
			__(
				'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
				'woocommerce-payments'
			),
			data.caseNumber,
			data.transactionId,
			data.transactionDate
		) }
${ sprintf(
	/* translators: %1$s: order date, %2$s: refund amount */
	__(
		"Our records indicate that this charge was a duplicate of a previous transaction. A refund has already been issued to the customer on %1$s for the amount of %2$s. This refund should be visible on the customer's statement within 7 - 10 business days.",
		'woocommerce-payments'
	),
	data.orderDate,
	dispute.amount
		? `${ ( dispute.amount / 100 ).toFixed(
				2
		  ) } ${ dispute.currency?.toUpperCase() }`
		: __( '[Refund Amount]', 'woocommerce-payments' )
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
	}

	// is_not_duplicate
	return `${ sprintf(
		/* translators: %1$s: case number, %2$s: transaction ID, %3$s: transaction date */
		__(
			'We are submitting evidence in response to chargeback #%1$s for transaction #%2$s on %3$s.',
			'woocommerce-payments'
		),
		data.caseNumber,
		data.transactionId,
		data.transactionDate
	) }
${ sprintf(
	/* translators: %1$s: case number, %2$s: transaction ID */
	__(
		'Our records show that the customer placed two distinct orders: %1$s and %2$s. Both transactions were legitimate, fulfilled independently, and are not duplicates.',
		'woocommerce-payments'
	),
	data.caseNumber,
	data.transactionId
) }

${ __(
	'To support our case, we are providing the following documentation:',
	'woocommerce-payments'
) }
${ attachmentsList }

${ __(
	'Based on this information, we respectfully request that the chargeback be reversed. Please let us know if any further details are required.',
	'woocommerce-payments'
) }`;
};

const bodyGenerators: { [ reason: string ]: CallableFunction } = {
	product_not_received: generateBodyProductNotReceived,
	credit_not_processed: generateBodyCreditNotProcessed,
	product_unacceptable: generateBodyProductUnacceptable,
	subscription_canceled: generateBodySubscriptionCanceled,
	duplicate: generateBodyDuplicate,
	fraudulent: generateBodyUnrecognized,
	unrecognized: generateBodyUnrecognized,
};

export const generateBody = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
) => {
	const handler = bodyGenerators[ dispute.reason ] || generateBodyGeneral;
	return handler( data, dispute, attachmentsList );
};

export const generateClosing = ( data: CoverLetterData ): string => {
	return `${ __( 'Thank you,', 'woocommerce-payments' ) }
${ data.merchantName }`;
};

// --- Main Cover Letter Generation ---

export const generateCoverLetter = (
	dispute: ExtendedDispute,
	accountDetails: AccountDetails,
	settings: any,
	bankName: string | null,
	refundStatus?: string,
	duplicateStatus?: string
): string => {
	const todayUnixTimestamp = Math.floor( Date.now() / 1000 );
	const todayFormatted = formatDateTimeFromTimestamp( todayUnixTimestamp, {
		separator: ', ',
		includeTime: false,
	} );
	const data: CoverLetterData = {
		merchantAddress: formatMerchantAddress( accountDetails ),
		merchantName: accountDetails.name,
		merchantEmail:
			settings?.account_business_support_email ||
			__( '<business@email.com>', 'woocommerce-payments' ),
		merchantPhone:
			settings?.account_business_support_phone ||
			__( '<Business Phone Number>', 'woocommerce-payments' ),
		today: todayFormatted,
		acquiringBank: bankName || __( '<Bank Name>', 'woocommerce-payments' ),
		caseNumber:
			dispute?.id || __( '<Case Number>', 'woocommerce-payments' ),
		transactionId:
			dispute?.charge?.id ||
			__( '<Transaction ID>', 'woocommerce-payments' ),
		transactionDate: dispute?.created
			? formatDateTimeFromTimestamp( dispute.created, {
					separator: ', ',
					includeTime: true,
			  } )
			: __( '<Transaction Date>', 'woocommerce-payments' ),
		customerName:
			dispute?.charge?.billing_details?.name ||
			__( '<Customer Name>', 'woocommerce-payments' ),
		product:
			dispute?.evidence?.product_description &&
			isEvidenceString( dispute.evidence.product_description )
				? dispute.evidence.product_description
				: dispute?.charge?.level3?.line_items
						?.map( ( item: any ) => item.product_description )
						.filter( Boolean )
						.join( ', ' ) ||
				  __( '<Product>', 'woocommerce-payments' ),
		orderDate: dispute?.charge?.created
			? formatDateTimeFromTimestamp( dispute.charge.created, {
					separator: ', ',
					includeTime: true,
			  } )
			: __( '<Order Date>', 'woocommerce-payments' ),
		deliveryDate: formatDeliveryDate(
			dispute?.evidence?.shipping_date &&
				isEvidenceString( dispute.evidence.shipping_date )
				? dispute.evidence.shipping_date
				: undefined
		),
		refundStatus: refundStatus,
		duplicateStatus: duplicateStatus,
	};

	const attachmentsList = generateAttachments( dispute, duplicateStatus );
	const header = generateHeader( data );
	const recipient = generateRecipient( data );
	const greeting = __(
		'Dear Dispute Resolution Team,',
		'woocommerce-payments'
	);
	const body = generateBody( data, dispute, attachmentsList );
	const closing = generateClosing( data );

	return `${ header }

${ recipient }

${ greeting }

${ body }

${ closing }`;
};
