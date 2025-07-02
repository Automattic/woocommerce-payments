/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
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

export const generateAttachments = ( dispute: ExtendedDispute ): string => {
	const attachments: string[] = [];
	let attachmentCount = 0;

	// Standard attachment logic for other dispute reasons
	const standardAttachments = [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Cancellation policy', 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Proof of active subscription', 'woocommerce-payments' ),
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Other documents', 'woocommerce-payments' ),
		},
	] as const;

	standardAttachments.forEach( ( { key, label } ) => {
		const evidence = dispute.evidence?.[ key ];
		if ( evidence && isEvidenceString( evidence ) ) {
			attachmentCount++;
			attachments.push(
				`• ${ label } (${ __(
					'Attachment',
					'woocommerce-payments'
				) } ${ String.fromCharCode( 64 + attachmentCount ) })`
			);
		}
	} );

	// If no attachments were provided, use default list
	if ( attachments.length === 0 ) {
		return `• ${ __(
			'<Attachment description>',
			'woocommerce-payments'
		) } (${ __( 'Attachment', 'woocommerce-payments' ) } A)
• ${ __( '<Attachment description>', 'woocommerce-payments' ) } (${ __(
			'Attachment',
			'woocommerce-payments'
		) } B)`;
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
	return `${ __( 'To:', 'woocommerce-payments' ) } ${ data.acquiringBank }
${ __( 'Subject:', 'woocommerce-payments' ) } ${ __(
		'Chargeback Dispute',
		'woocommerce-payments'
	) } – ${ __( 'Case', 'woocommerce-payments' ) } #${ data.caseNumber }`;
};

export const generateBody = (
	data: CoverLetterData,
	dispute: ExtendedDispute,
	attachmentsList: string
): string => {
	if ( dispute.reason === 'credit_not_processed' ) {
		if ( data.refundStatus === 'refund_has_been_issued' ) {
			return `${ __(
				'We are submitting evidence in response to chargeback',
				'woocommerce-payments'
			) } #${ data.caseNumber } ${ __(
				'for transaction',
				'woocommerce-payments'
			) } #${ data.transactionId } ${ __(
				'on',
				'woocommerce-payments'
			) } ${ data.transactionDate }.

${ __( 'Our records indicate that the customer,', 'woocommerce-payments' ) } ${
				data.customerName
			}, ${ __( 'was refunded on', 'woocommerce-payments' ) } ${
				data.orderDate
			} ${ __( 'for the amount of', 'woocommerce-payments' ) } ${
				dispute.amount
					? `${ ( dispute.amount / 100 ).toFixed(
							2
					  ) } ${ dispute.currency?.toUpperCase() }`
					: __( '[Refund Amount]', 'woocommerce-payments' )
			}. ${ __(
				"The refund was processed through our payment provider and should be visible on the customer's statement within 7 - 10 business days.",
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
		}
		// refund_was_not_owed
		return `${ __(
			'We are submitting evidence in response to chargeback',
			'woocommerce-payments'
		) } #${ data.caseNumber } ${ __(
			'for transaction',
			'woocommerce-payments'
		) } #${ data.transactionId } ${ __( 'on', 'woocommerce-payments' ) } ${
			data.transactionDate
		}.
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
	}

	if ( dispute.reason === 'product_not_received' ) {
		return `${ __(
			'We are submitting evidence in response to chargeback',
			'woocommerce-payments'
		) } #${ data.caseNumber } ${ __(
			'for transaction',
			'woocommerce-payments'
		) } #${ data.transactionId } ${ __( 'on', 'woocommerce-payments' ) } ${
			data.transactionDate
		}.

${ __( 'Our records indicate that the customer,', 'woocommerce-payments' ) } ${
			data.customerName
		}, ${ __( 'ordered', 'woocommerce-payments' ) } ${ data.product } ${ __(
			'on',
			'woocommerce-payments'
		) } ${ data.orderDate } ${ __(
			'and received it on',
			'woocommerce-payments'
		) } ${ data.deliveryDate }.

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

	if ( dispute.reason === 'product_unacceptable' ) {
		return `${ __(
			'We are submitting evidence in response to chargeback',
			'woocommerce-payments'
		) } #${ data.caseNumber } ${ __(
			'for transaction',
			'woocommerce-payments'
		) } #${ data.transactionId } ${ __( 'on', 'woocommerce-payments' ) } ${
			data.transactionDate
		}.

${ __( 'Our records indicate that the customer,', 'woocommerce-payments' ) } ${
			data.customerName
		}, ${ __( 'ordered', 'woocommerce-payments' ) } ${ data.product } ${ __(
			'on',
			'woocommerce-payments'
		) } ${ data.orderDate }. ${ __(
			'The product matched the description provided at the time of sale, and we did not receive any indication from the customer that it was defective or not as described.',
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
	}

	return `${ __(
		'We are submitting evidence in response to chargeback',
		'woocommerce-payments'
	) } #${ data.caseNumber } ${ __(
		'for transaction',
		'woocommerce-payments'
	) } #${ data.transactionId } ${ __( 'on', 'woocommerce-payments' ) } ${
		data.transactionDate
	}.

${ __(
	'Our records indicate that the customer and legitimate cardholder,',
	'woocommerce-payments'
) } ${ data.customerName }, ${ __( 'ordered', 'woocommerce-payments' ) } ${
		data.product
	} ${ __( 'on', 'woocommerce-payments' ) } ${ data.orderDate }.

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
	refundStatus?: string
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
	};

	const attachmentsList = generateAttachments( dispute );
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
