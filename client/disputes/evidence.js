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
	const { evidence, showPlaceholder } = props;

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
					disabled
				/>
			</Card>
			<Card>
				<Button isPrimary isLarge>{ __( 'Submit Evidence' ) }</Button>
				<Button isDefault isLarge>{ __( 'Save For Later' ) }</Button>
			</Card>
		</Section>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const fetchDispute = async () => {
		setLoading( true );
		setDispute( await apiFetch( { path } ) );
		setLoading( false );
	};
	useEffect( () => { fetchDispute() }, [] );

	return (
		<DisputeEvidenceForm
			showPlaceholder={ loading }
			evidence={ dispute ? dispute.evidence : {} }
		/>
	);
};
