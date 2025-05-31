/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	TextareaControl,
	Button,
	Icon,
} from 'wcpay/components/wp-components-wrapped';
import { external } from '@wordpress/icons';

interface CoverLetterProps {
	value: string;
	onChange: ( value: string ) => void;
}

const CoverLetter: React.FC< CoverLetterProps > = ( { value, onChange } ) => {
	return (
		<section className="wcpay-dispute-evidence-cover-letter">
			<TextareaControl
				label={ __( 'COVER LETTER', 'woocommerce-payments' ) }
				value={ value }
				onChange={ onChange }
				rows={ 30 }
				className="wcpay-dispute-evidence-cover-letter__textarea"
			/>
			<Button
				className="wcpay-dispute-evidence-cover-letter__print"
				variant="primary"
				onClick={ () => window.print() }
				icon={ <Icon icon={ external } size={ 24 } /> }
			>
				{ __( 'View cover letter', 'woocommerce-payments' ) }
			</Button>
			{ /* Print-only formatted letter */ }
			<pre className="wcpay-dispute-evidence-cover-letter__printonly">
				{ value }
			</pre>
		</section>
	);
};

export default CoverLetter;
