/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { Card } from '@woocommerce/components';

import { FileUploadControl } from './file-upload';

/**
 * Internal dependencies.
 */
import '../style.scss';
import evidenceFields from './fields';
import Page from '../../components/page';
import CardFooter from '../../components/card-footer';

export const DisputeEvidenceForm = props => {
	const {
		evidence,
		showPlaceholder,
		onChange,
		onFileChange,
		onFileRemove,
		onSave,
		readOnly,
	} = props;

	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	const composeDefaultControlProps = field => ( {
		label: field.display,
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
			isLoading,
			isDone,
			error,
		};
	};

	const composeFieldControl = field => {
		let Control = TextareaControl;
		let controlProps = composeDefaultControlProps( field );

		switch ( field.control ) {
			case 'file':
				Control = FileUploadControl;
				controlProps = composeFileUploadProps( field );
				break;
			case 'text':
				Control = TextControl;
				break;
		}

		return <Control key={ field.key } { ...controlProps } />;
	};

	const evidenceSections = evidenceFields.map( section => {
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
		<Page isNarrow className="wcpay-dispute-evidence">
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
		</Page>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ evidence, setEvidence ] = useState( {} ); // Evidence to update.
	const { createSuccessNotice, createErrorNotice } = useDispatch( 'core/notices' );

	const fetchDispute = async () => {
		setLoading( true );
		try {
			setDispute( await apiFetch( { path } ) );
		} finally {
			setLoading( false );
		}
	};
	useEffect( () => {
		fetchDispute();
	}, [] );

	const doRemoveFile = async ( key ) => {
		dispute.metadata[ key ] = '';
		setEvidence( e => ( { ...e, [ key ]: '' } ) );
	};

	const doUploadFile = async ( key, file ) => {
		if ( ! file ) {
			return;
		}

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', 'dispute_evidence' );

		// Set request status for UI.
		dispute.isUploading = {
			...dispute.isUploading,
			[ key ]: true,
		};
		dispute.uploadingErrors = {
			...dispute.uploadingErrors,
			[ key ]: '',
		};

		// Force reload evidence components.
		setEvidence( e => ( { ...e, [ key ]: '' } ) );

		apiFetch( { path: '/wc/v3/payments/file', method: 'post', body } )
			.then( r => {
				dispute.metadata[ key ] = r.filename;
				dispute.isUploading[ key ] = false;

				setEvidence( e => ( { ...e, [ key ]: r.id } ) );
			} )
			.catch( err => {
				dispute.isUploading[ key ] = false;
				dispute.uploadingErrors[ key ] = err.message;

				// Force reload evidence components.
				setEvidence( e => ( { ...e, [ key ]: '' } ) );
			} );
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

	return (
		<DisputeEvidenceForm
			showPlaceholder={ loading }
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
			onChange={ ( key, value ) => setEvidence( e => ( { ...e, [ key ]: value } ) ) }
			onFileChange={ doUploadFile }
			onSave={ doSave }
			onFileRemove={ doRemoveFile }
			readOnly={ dispute && dispute.status.indexOf( 'needs_response' ) === -1 }
		/>
	);
};
