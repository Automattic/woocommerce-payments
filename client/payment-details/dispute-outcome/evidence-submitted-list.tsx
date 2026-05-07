/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, _x, sprintf } from '@wordpress/i18n';
import { Icon, check, closeSmall } from '@wordpress/icons';
import { VisuallyHidden } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type {
	EvidenceFieldState,
	EvidenceFieldStatus,
} from 'wcpay/disputes/new-evidence/types';
import './style.scss';

interface Props {
	fields: EvidenceFieldStatus[];
}

const stateModifiers: Record< EvidenceFieldState, string > = {
	provided: 'provided',
	expected_missing: 'expected-missing',
	optional_missing: 'optional-missing',
};

const renderStateLabel = ( state: EvidenceFieldState ): string => {
	switch ( state ) {
		case 'provided':
			return __( 'Provided', 'woocommerce-payments' );
		case 'expected_missing':
		case 'optional_missing':
			return __( 'Not provided', 'woocommerce-payments' );
	}
};

// Severity qualifier surfaced only to screen readers. The visible copy is
// unified ("Not provided") per the mock; sighted users get the severity from
// icon shape and color. SR users have neither, so the qualifier is what makes
// the tri-state distinguishable to them.
//
// The whole suffix (parens + spacing + word) is one translatable string via
// sprintf so locales can adapt the punctuation; the severity word itself uses
// _x for context so translators know which sense of "required"/"optional" is
// meant.
const renderSeverityHint = ( state: EvidenceFieldState ): string | null => {
	switch ( state ) {
		case 'expected_missing':
			return sprintf(
				/* translators: %s: severity label ("required" or "optional"); the whole phrase is hidden visually and read only by screen readers as the suffix to "Not provided". */
				__( ' (%s)', 'woocommerce-payments' ),
				_x(
					'required',
					'severity of a missing evidence field, read by screen readers',
					'woocommerce-payments'
				)
			);
		case 'optional_missing':
			return sprintf(
				/* translators: %s: severity label ("required" or "optional"); the whole phrase is hidden visually and read only by screen readers as the suffix to "Not provided". */
				__( ' (%s)', 'woocommerce-payments' ),
				_x(
					'optional',
					'severity of a missing evidence field, read by screen readers',
					'woocommerce-payments'
				)
			);
		case 'provided':
			return null;
	}
};

const renderStateIcon = ( state: EvidenceFieldState ): JSX.Element => {
	switch ( state ) {
		case 'provided':
			return <Icon icon={ check } />;
		case 'expected_missing':
			return <Icon icon={ closeSmall } />;
		case 'optional_missing':
			return <>—</>;
	}
};

const EvidenceSubmittedList: React.FC< Props > = ( { fields } ) => {
	if ( fields.length === 0 ) {
		return null;
	}

	return (
		<ul className="dispute-outcome-evidence-list">
			{ fields.map( ( { key, label, state } ) => (
				<li
					key={ key }
					className={ `dispute-outcome-evidence-list__item dispute-outcome-evidence-list__item--${ stateModifiers[ state ] }` }
				>
					<span
						className="dispute-outcome-evidence-list__icon"
						aria-hidden="true"
					>
						{ renderStateIcon( state ) }
					</span>
					<span className="dispute-outcome-evidence-list__text">
						<span className="dispute-outcome-evidence-list__label">
							{ label }
						</span>
						{ /* Explicit space between label and state so screen
						   readers get a word boundary rather than concatenating
						   the two phrases. */ }{ ' ' }
						{ state === 'provided' ? (
							<VisuallyHidden>
								{ renderStateLabel( state ) }
							</VisuallyHidden>
						) : (
							<span className="dispute-outcome-evidence-list__state">
								<span aria-hidden="true">{ '— ' }</span>
								{ renderStateLabel( state ) }
								<VisuallyHidden>
									{ renderSeverityHint( state ) }
								</VisuallyHidden>
							</span>
						) }
					</span>
				</li>
			) ) }
		</ul>
	);
};

export default EvidenceSubmittedList;
