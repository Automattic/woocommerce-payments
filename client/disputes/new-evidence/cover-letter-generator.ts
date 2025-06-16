/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import type { ExtendedDispute, AccountDetails, CoverLetterData } from './types';

// --- Utility Functions ---

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

	// For product not received disputes, prioritize shipping and delivery evidence
	if ( dispute.reason === 'product_not_received' ) {
		if (
			dispute.evidence?.receipt &&
			isEvidenceString( dispute.evidence.receipt )
		) {
			attachmentCount++;
			attachments.push(
				`• ${ __(
					'Proof of Purchase: Receipt and payment confirmation',
					'woocommerce-payments'
				) } (${ __(
					'Attachment',
					'woocommerce-payments'
				) } ${ String.fromCharCode( 64 + attachmentCount ) })`
			);
		}
		if (
			dispute.evidence?.shipping_documentation &&
			isEvidenceString( dispute.evidence.shipping_documentation )
		) {
			attachmentCount++;
			attachments.push(
				`• ${ __(
					'Proof of Shipping: Tracking details',
					'woocommerce-payments'
				) } (${ __(
					'Attachment',
					'woocommerce-payments'
				) } ${ String.fromCharCode( 64 + attachmentCount ) })`
			);
		}
		if (
			dispute.evidence?.uncategorized_file &&
			isEvidenceString( dispute.evidence.uncategorized_file )
		) {
			attachmentCount++;
			attachments.push(
				`• ${ __(
					'Proof of Delivery: Delivery confirmation receipt',
					'woocommerce-payments'
				) } (${ __(
					'Attachment',
					'woocommerce-payments'
				) } ${ String.fromCharCode( 64 + attachmentCount ) })`
			);
		}
	} else {
		// Standard attachment logic for other dispute reasons
		const standardAttachments = [
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
				label: __( 'Store refund policy', 'woocommerce-payments' ),
			},
			{
				key: 'shipping_documentation',
				label: __( 'Proof of shipping', 'woocommerce-payments' ),
			},
			{
				key: 'service_documentation',
				label: __( 'Service documentation', 'woocommerce-payments' ),
			},
			{
				key: 'cancellation_policy',
				label: __( 'Cancellation policy', 'woocommerce-payments' ),
			},
			{
				key: 'access_activity_log',
				label: __( 'Access activity log', 'woocommerce-payments' ),
			},
			{
				key: 'uncategorized_file',
				label: __( 'Additional documentation', 'woocommerce-payments' ),
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
	}

	// If no attachments were provided, use default list
	if ( attachments.length === 0 ) {
		if ( dispute.reason === 'product_not_received' ) {
			return `• ${ __(
				'Proof of Purchase: Receipt and payment confirmation',
				'woocommerce-payments'
			) } (${ __( 'Attachment', 'woocommerce-payments' ) } A)
• ${ __(
				'Proof of Shipping: Tracking details',
				'woocommerce-payments'
			) } (${ __( 'Attachment', 'woocommerce-payments' ) } B)
• ${ __(
				'Proof of Delivery: Delivery confirmation receipt',
				'woocommerce-payments'
			) } (${ __( 'Attachment', 'woocommerce-payments' ) } C)`;
		}
		return `• ${ __(
			'AVS/CVV Match: Billing address and security code matched',
			'woocommerce-payments'
		) } (${ __( 'Attachment', 'woocommerce-payments' ) } A)
• ${ __(
			'IP/Device Data: Location and device info used at purchase',
			'woocommerce-payments'
		) } (${ __( 'Attachment', 'woocommerce-payments' ) } B)
• ${ __(
			'Customer Confirmation: Email or chat confirming purchase',
			'woocommerce-payments'
		) } (${ __( 'Attachment', 'woocommerce-payments' ) } C)
• ${ __(
			'Usage Data: Login records for the digital goods',
			'woocommerce-payments'
		) } (${ __( 'Attachment', 'woocommerce-payments' ) } D)`;
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
	bankName: string | null
): string => {
	const data: CoverLetterData = {
		merchantAddress: formatMerchantAddress( accountDetails ),
		merchantName: accountDetails.name,
		merchantEmail:
			settings?.account_business_support_email ||
			__( '<business@email.com>', 'woocommerce-payments' ),
		merchantPhone:
			settings?.account_business_support_phone ||
			__( '<Business Phone Number>', 'woocommerce-payments' ),
		today: formatDateTimeFromTimestamp( Date.now(), {
			separator: ', ',
			includeTime: true,
		} ),
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
