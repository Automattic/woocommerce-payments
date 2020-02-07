/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { Section, Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import './style.scss';
import evidenceFields from './fields';

export const DisputeEvidenceForm = props => {
	const { evidence, showPlaceholder, onChange, onSave, readOnly } = props;

	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

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

	return (
		<Section>
			{ evidenceSections }
			{ readOnly ? null : (
				<Card>
					<p>
						{ __(
							'When you submit your evidence, we\'ll compile and send it to the cardholder\'s bank, ' +
							'and then email you once the dispute has been decided.',
							'woocommerce-payments'
						) }
						&nbsp;
						<strong>{ __( 'Evidence submission is final.', 'woocommerce-payments' ) }</strong>
					</p>
					<p>
						{ __(
							'You can also save this evidence for later editing instead of submitting it immediately.',
							'woocommerce-payments'
						) }
						&nbsp;
						<strong>{__( 'We will automatically submit any saved evidence at the due date.', 'woocommerce-payments' )}</strong>
					</p>

					<Button isPrimary isLarge onClick={ () => onSave( true ) }>{ __( 'Submit Evidence' ) }</Button>
					<Button isDefault isLarge onClick={ () => onSave( false ) }>{ __( 'Save For Later' ) }</Button>
				</Card>
			) }
		</Section>
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
		setDispute( await apiFetch( { path } ) );
		setLoading( false );
	};
	useEffect( () => {
		fetchDispute();
	}, [] );

	const doSave = async ( submit ) => {
		setLoading( true );
		setDispute( await apiFetch( { path, method: 'post', data: { evidence, submit } } ) );
		setLoading( false );
		setEvidence( {} );
	};

	return (
		<DisputeEvidenceForm
			showPlaceholder={ loading }
			evidence={ dispute ? { ...dispute.evidence, ...evidence } : {} }
			onChange={ ( key, value ) => setEvidence( { ...evidence, [ key ]: value } ) }
			onSave={ doSave }
			readOnly={ dispute && dispute.status.indexOf( 'needs_response' ) === -1 }
		/>
	);
};
