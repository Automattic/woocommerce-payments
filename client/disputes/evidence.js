/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextareaControl } from '@wordpress/components';
import { Section, Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import './style.scss';

export const DisputeEvidenceForm = props => {
	const { evidence, showPlaceholder, onChange, onSave } = props;

	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	const key = 'uncategorized_text';

	return (
		<Section>
			<Card title={ __( 'Additional details' ) }>
				<TextareaControl
					key={ key }
					label="Additional Details"
					value={ evidence[ key ] || '' }
					onChange={ value => onChange( key, value ) }
				/>
			</Card>
			<Card>
				<Button isPrimary isLarge onClick={ () => onSave( true ) }>{ __( 'Submit Evidence' ) }</Button>
				<Button isDefault isLarge onClick={ () => onSave( false ) }>{ __( 'Save For Later' ) }</Button>
			</Card>
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
	useEffect( () => { fetchDispute() }, [] );

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
		/>
	);
};
