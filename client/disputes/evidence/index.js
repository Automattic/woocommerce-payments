/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl, BaseControl, FormFileUpload } from '@wordpress/components';
import { Section, Card } from '@woocommerce/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies.
 */
import '../style.scss';
import evidenceFields from './fields';
import Page from '../../components/page';
import CardFooter from '../../components/card-footer';

export const DisputeEvidenceForm = props => {
	const { evidence, showPlaceholder, onChange, onFileChange, onSave, readOnly } = props;

	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	const evidenceSections = evidenceFields.map( section => {
		return (
			<Card key={ section.key } title={ section.title }>
				{
					section.fields.map( field => {
						if ( field.control === 'file' ) {
							return (
								<BaseControl
									key={ field.key }
									id={ 'form-file-upload-' + field.key }
									label={ field.display }
									help={ field.description }
								>
									<FormFileUpload
										id={ 'form-file-upload-' + field.key }
										isLarge
										isDefault
										icon={ <Gridicon icon="add-outline" size={ 18 } /> }
										accept=".pdf, image/png, image/jpeg"
										onChange={ ( event ) => onFileChange( field.key, event.target.files[ 0 ] ) }
									>
										{ __( 'Upload File', 'woocommerce-payments' ) }
									</FormFileUpload>
								</BaseControl>
							);
						}

						const Control = field.control === 'text' ? TextControl : TextareaControl;
						return (
							<Control
								key={ field.key }
								label={ field.display }
								value={ evidence[ field.key ] || '' }
								onChange={ value => onChange( field.key, value ) }
								disabled={ readOnly }
								help={ field.description }
							/>
						);
					} )
				}
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

	const doUploadFile = async ( key, file ) => {
		let fileId = null;
		if ( file ) {
			const body = new FormData();
			body.append( 'file', file );
			// body.append( 'purpose', 'dispute_evidence' );
			// body.append( 'file_link_data.create', 'true' );
			const response = await apiFetch( { path: '/wc/v3/payments/file', method: 'post', body } );
			fileId = response.id;
		}

		setEvidence( evidence => ( { ...evidence, [ key ]: fileId } ) )
	}

	const doSave = async ( submit ) => {
		setLoading( true );
		try {
			setDispute( await apiFetch( { path, method: 'post', data: { evidence, submit } } ) );
		} finally {
			setLoading( false );
			setEvidence( {} );
		}
	};

	return (
		<DisputeEvidenceForm
			showPlaceholder={ loading }
			evidence={ dispute ? { ...dispute.evidence, ...evidence } : {} }
			onChange={ ( key, value ) => setEvidence( evidence => ( { ...evidence, [ key ]: value } ) ) }
			onFileChange={ doUploadFile }
			onSave={ doSave }
			readOnly={ dispute && dispute.status.indexOf( 'needs_response' ) === -1 }
		/>
	);
};
