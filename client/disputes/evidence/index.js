/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { getHistory } from '@woocommerce/navigation';
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
} from '@wordpress/components';
import { merge, some, flatten, isMatchWith } from 'lodash';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import '../style.scss';
import { useDisputeEvidence } from 'wcpay/data';
import evidenceFields from './fields';
import { FileUploadControl } from './file-upload';
import Info from '../info';
import Page from 'components/page';
import Loadable, { LoadableBlock } from 'components/loadable';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';

const DISPUTE_EVIDENCE_MAX_LENGTH = 150000;
const PRODUCT_TYPE_META_KEY = '__product_type';

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
				'string' === typeof cur ? acc + cur.length : acc,
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
		help: expandHelp( field.description ),
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
		const isDone = ! isLoading && 0 < fileName.length;
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
			help: expandHelp( field.description ),
		};
	};

	const composeFieldControl = ( field ) => {
		switch ( field.type ) {
			case 'file':
				return (
					<FileUploadControl
						key={ field.key }
						{ ...composeFileUploadProps( field ) }
					/>
				);
			case 'text':
				return (
					<TextControl
						key={ field.key }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
			case 'date':
				return (
					<TextControl
						key={ field.key }
						type={ 'date' }
						max={ moment().format( 'YYYY-MM-DD' ) }
						{ ...composeDefaultControlProps( field ) }
					/>
				);
			default:
				return (
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
					{ section.description && <p>{ section.description }</p> }
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
		'needs_response' !== dispute.status &&
		'warning_needs_response' !== dispute.status;
	const disputeIsAvailable = ! isLoading && dispute.id;
	const testModeNotice = <TestModeNotice topic={ topics.disputeDetails } />;

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				{ testModeNotice }
				<div>
					{ __( 'Dispute not loaded', 'woocommerce-payments' ) }
				</div>
			</Page>
		);
	}

	return (
		<Page isNarrow className="wcpay-dispute-evidence">
			{ testModeNotice }
			<Card size="large">
				<CardHeader>
					{
						<Loadable
							isLoading={ isLoading }
							value={ __(
								'Challenge dispute',
								'woocommerce-payments'
							) }
						/>
					}
				</CardHeader>
				<CardBody>
					<Info dispute={ dispute } isLoading={ isLoading } />
				</CardBody>
			</Card>
			<Card size="large">
				<CardHeader>
					{
						<Loadable
							isLoading={ isLoading }
							value={ __(
								'Product type',
								'woocommerce-payments'
							) }
						/>
					}
				</CardHeader>
				<CardBody>
					<LoadableBlock isLoading={ isLoading } numLines={ 2 }>
						<SelectControl
							value={ productType }
							onChange={ onChangeProductType }
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
					</LoadableBlock>
				</CardBody>
			</Card>
			{
				// Don't render the form placeholder while the dispute is being loaded.
				// The form content depends on the selected product type, hence placeholder might disappear after loading.
				! isLoading && (
					<DisputeEvidenceForm
						{ ...evidenceFormProps }
						readOnly={ readOnly }
					/>
				)
			}
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
				if ( null === disputeValue && ! formValue ) {
					return true;
				}
			}
		);

	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( pristine ) {
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

		wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_started', {
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

			wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_success', {
				type: key,
			} );
		} catch ( err ) {
			wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_failed', {
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
		const href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/disputes',
		} );

		wcpayTracks.recordEvent(
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

		getHistory().push( href );
	};

	const handleSaveError = ( err, submit ) => {
		wcpayTracks.recordEvent(
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
			wcpayTracks.recordEvent(
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
			handleSaveSuccess( submit );
			setEvidence( {} );
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
		wcpayTracks.recordEvent( 'wcpay_dispute_product_selected', properties );
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
