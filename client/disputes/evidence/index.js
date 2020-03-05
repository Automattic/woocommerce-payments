/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import { Card } from '@woocommerce/components';
import { merge, some } from 'lodash';

/**
 * Internal dependencies.
 */
import '../style.scss';
import evidenceFields from './fields';
import { FileUploadControl } from './file-upload';
import Info from '../info';
import Page from 'components/page';
import CardFooter from 'components/card-footer';

const PRODUCT_TYPE_META_KEY = '__product_type';

export const DisputeEvidenceForm = props => {
	const { fields, evidence, onChange, onFileChange, onFileRemove, onSave, readOnly } = props;

	if ( ! fields || ! fields.length ) {
		return null;
	}

	const composeDefaultControlProps = field => ( {
		label: field.label,
		value: evidence[ field.key ] || '',
		onChange: value => onChange( field.key, value ),
		disabled: readOnly,
		help: field.description,
	} );

	const composeFileUploadProps = field => {
		const fileName = ( evidence.metadata && evidence.metadata[ field.key ] ) || '';
		const isLoading = evidence.isUploading && ( evidence.isUploading[ field.key ] || false );
		const error = evidence.uploadingErrors && ( evidence.uploadingErrors[ field.key ] || '' );
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
		};
	};

	const composeFieldControl = field => {
		switch ( field.type ) {
			case 'file':
				return <FileUploadControl key={ field.key } { ...composeFileUploadProps( field ) } />;
			case 'text':
				return <TextControl key={ field.key } { ...composeDefaultControlProps( field ) } />;
			default:
				return <TextareaControl key={ field.key } { ...composeDefaultControlProps( field ) } />;
		}
	};

	const evidenceSections = fields.map( section => {
		return (
			<Card key={ section.key } title={ section.title }>
				{ section.fields.map( composeFieldControl ) }
			</Card>
		);
	} );

	const confirmMessage = __(
		"Are you sure you're ready to submit this evidence? Evidence submissions are final.",
		'woocommerce-payments'
	);
	const handleSubmit = () => window.confirm( confirmMessage ) && onSave( true );

	return (
		<>
			{ evidenceSections }
			{ readOnly ? null : (
				<Card>
					<p>
						{ __(
							// eslint-disable-next-line max-len
							"When you submit your evidence, we'll format it and send it to the cardholder's bank, then email you once the dispute has been decided.",
							'woocommerce-payments'
							) }
					</p>
					<p>
						<strong>{ __( 'Evidence submission is final.', 'woocommerce-payments' ) }</strong>
						{ ' ' }
						{ __(
							'You can also save this evidence for later instead of submitting it immediately.',
							'woocommerce-payments'
							) }
						{ ' ' }
						<strong>{__( 'We will automatically submit any saved evidence at the due date.', 'woocommerce-payments' )}</strong>
					</p>

					<CardFooter>
						<Button isPrimary isLarge onClick={ handleSubmit }>
							{__( 'Submit Evidence', 'woocommerce-payments' )}
						</Button>
						<Button isDefault isLarge onClick={ () => onSave( false ) }>
							{__( 'Save For Later', 'woocommerce-payments' )}
						</Button>
					</CardFooter>
				</Card>
			) }
		</>
	);
};

export const DisputeEvidencePage = props => {
	const { showPlaceholder, dispute, productType, onChangeProductType, ...evidenceFormProps } = props;

	if ( showPlaceholder ) {
		// TODO Render proper placeholder view.
		return <div>Loading…</div>;
	}
	if ( dispute == null ) {
		return <div>Dispute not loaded</div>;
	}

	const readOnly = dispute && 'needs_response' !== dispute.status && 'warning_needs_response' !== dispute.status;

	return (
		<Page isNarrow className="wcpay-dispute-evidence">
			<Card title={ __( 'Challenge Dispute', 'woocommerce-payments' ) }>
				<Info dispute={ dispute } />
			</Card>

			<Card title={ __( 'Product Type', 'woocommerce-payments' ) }>
				<SelectControl
					value={ productType }
					onChange={ onChangeProductType }
					options={ [
						{ label: __( 'Select one…', 'woocommerce-payments' ), disabled: true, value: '' },
						{ label: __( 'Physical product', 'woocommerce-payments' ), value: 'physical_product' },
						{ label: __( 'Digital product or service', 'woocommerce-payments' ), value: 'digital_product_or_service' },
						{ label: __( 'Offline service', 'woocommerce-payments' ), value: 'offline_service' },
					] }
					disabled={ readOnly }
				/>
			</Card>

			<DisputeEvidenceForm
				{ ...evidenceFormProps }
				readOnly={ readOnly }
			/>
		</Page>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ evidence, setEvidence ] = useState( {} ); // Evidence to update.
	const { createSuccessNotice, createErrorNotice, createInfoNotice } = useDispatch( 'core/notices' );

	const fetchDispute = async () => {
		setLoading( true );
		try {
			const fetchedDispute = await apiFetch( { path } );
			const fetchedEvidence = fetchedDispute && fetchedDispute.evidence || {};
			setDispute( fetchedDispute );
			// Updated fetched evidence to allow submission without UI changes.
			setEvidence( fetchedEvidence );
		} finally {
			setLoading( false );
		}
	};
	useEffect( () => {
		fetchDispute();
	}, [] );

	const updateEvidence = ( key, value ) => setEvidence( e => ( { ...e, [ key ]: value } ) );
	const updateDispute = ( updates = {} ) => setDispute( d => merge( {}, d, updates ) );
	const isUploadingEvidence = () => some( dispute.isUploading );

	const doRemoveFile = ( key ) => {
		updateEvidence( key, '' );
		updateDispute( {
			metadata: { [ key ]: '' },
			uploadingErrors: { [ key ]: '' },
		} );
	};

	const doUploadFile = async ( key, file ) => {
		if ( ! file ) {
			return;
		}

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', 'dispute_evidence' );

		// Set request status for UI.
		updateDispute( {
			isUploading: { [ key ]: true },
			uploadingErrors: { [ key ]: '' },
		} );

		// Force reload evidence components.
		updateEvidence( key, '' );

		try {
			const uploadedFile = await apiFetch( { path: '/wc/v3/payments/file', method: 'post', body } );
			// Store uploaded file name in metadata to display in submitted evidence or saved for later form.
			updateDispute( {
				metadata: { [ key ]: uploadedFile.filename },
				isUploading: { [ key ]: false },
			} );
			updateEvidence( key, uploadedFile.id );
		} catch ( err ) {
			updateDispute( {
				isUploading: { [ key ]: false },
				uploadingErrors: { [ key ]: err.message },
			} );

			// Force reload evidence components.
			updateEvidence( key, '' );
		}
	};

	const handleSaveSuccess = submit => {
		const message = submit
			? __( 'Evidence submitted!', 'woocommerce-payments' )
			: __( 'Evidence saved!', 'woocommerce-payments' );
		const href = addQueryArgs( 'admin.php', {
			page: 'wc-admin',
			path: '/payments/disputes',
		} );
		/*
			We rely on WC-Admin Transient notices to display success message.
			https://github.com/woocommerce/woocommerce-admin/tree/master/client/layout/transient-notices.
		*/
		createSuccessNotice( message );
		getHistory().push( href );
	};

	const handleSaveError = submit => {
		const message = submit
			? __( 'Failed to submit evidence!', 'woocommerce-payments' )
			: __( 'Failed to save evidence!', 'woocommerce-payments' );
		createErrorNotice( message );
	};

	const doSave = async submit => {
		// Prevent submit if upload is in progress.
		if ( isUploadingEvidence() ) {
			createInfoNotice( __( 'Please wait until file upload is finished' ), 'woocommerce-payments' );
			return;
		}

		setLoading( true );

		try {
			const { metadata } = dispute;
			const updatedDispute = await apiFetch( {
				path,
				method: 'post',
				data: { evidence, metadata, submit },
			} );
			setDispute( updatedDispute );
			handleSaveSuccess( submit );
			setEvidence( {} );
		} catch ( err ) {
			handleSaveError( submit );
		} finally {
			setLoading( false );
		}
	};

	const productType = dispute && dispute.metadata[ PRODUCT_TYPE_META_KEY ] || '';
	const updateProductType = ( newProductType ) => {
		setDispute( d => merge( {}, d, { metadata: { [ PRODUCT_TYPE_META_KEY ]: newProductType } } ) );
	};

	const fieldsToDisplay = useMemo(
		() => evidenceFields( dispute && dispute.reason, productType ),
		[ dispute && dispute.reason, productType ]
	);

	return (
		<DisputeEvidencePage
			showPlaceholder={ loading }
			dispute={ dispute }
			evidence={
				dispute
				? {
					...dispute.evidence,
					...evidence,
					metadata: dispute.metadata || {},
					isUploading: dispute.isUploading || {},
					uploadingErrors: dispute.uploadingErrors || {} }
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
