/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { Section, Card } from '@woocommerce/components';

const evidenceFields = [
	{
		key: 'general',
		title: __( 'General evidence' ),
		description: '',
		fields: [
			{
				key: 'product_description',
				display: 'Product Description',
				control: 'textarea',
			},
			{
				key: 'customer_name',
				display: 'Customer Name',
				control: 'text',
			},
			{
				key: 'customer_email_address',
				display: 'Customer Email',
				control: 'text',
			},
			// …
			{
				key: 'customer_purchase_ip',
				display: 'Customer IP Address',
				control: 'text',
			},
			// …
		],
	},
	{
		key: 'uncategorized',
		title: __( 'Additional details' ),
		fields: [
			{
				key: 'uncategorized_text',
				display: 'Additional Details',
				control: 'textarea',
			}
		],
	},
];

/**
 * Internal dependencies.
 */
import './style.scss';

export const DisputeEvidenceForm = props => {
	const { evidence, showPlaceholder, onChange, onSave } = props;

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
