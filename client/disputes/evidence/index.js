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

/**
 * Internal dependencies.
 */
import '../style.scss';
import evidenceFields from './fields';
import Info from '../info';
import Page from 'components/page';
import CardFooter from 'components/card-footer';

export const DisputeEvidenceForm = props => {
	const { evidence, onChange, onSave, readOnly } = props;

	const evidenceSections = evidenceFields.map( section => {
		return (
			<Card key={ section.key } title={ section.title }>
				{
					section.fields.map( field => {
						const Control = field.control === 'text' ? TextControl : TextareaControl;
						return (
							<Control
								key={ field.key }
								label={ field.display }
								value={ evidence[ field.key ] || '' }
								onChange={ value => onChange( field.key, value ) }
								disabled={ readOnly }
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
	const { showPlaceholder, dispute, evidence, onChange, onSave } = props;

	if ( showPlaceholder ) {
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

			<DisputeEvidenceForm
				evidence={ evidence }
				onChange={ onChange }
				onSave={ onSave }
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
			setDispute(
				await apiFetch( {
					path,
					method: 'post',
					data: { evidence, submit },
				} )
			);
			handleSaveSuccess( submit );
			setEvidence( {} );
		} catch ( err ) {
			handleSaveError( submit );
		} finally {
			setLoading( false );
		}
	};

	return (
		<DisputeEvidencePage
			showPlaceholder={ loading }
			dispute={ dispute }
			evidence={ dispute ? { ...dispute.evidence, ...evidence } : {} }
			onChange={ ( key, value ) => setEvidence( { ...evidence, [ key ]: value } ) }
			onSave={ doSave }
		/>
	);
};
