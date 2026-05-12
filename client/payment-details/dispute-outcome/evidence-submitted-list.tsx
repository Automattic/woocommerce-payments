/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Icon, check, closeSmall, lineSolid } from '@wordpress/icons';
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

const stateLabels: Record< EvidenceFieldState, string > = {
	provided: __( 'Provided', 'woocommerce-payments' ),
	expected_missing: __( 'Not provided', 'woocommerce-payments' ),
	optional_missing: __( 'Not provided', 'woocommerce-payments' ),
};

// Severity qualifier surfaced only to screen readers. The visible copy is
// unified ("Not provided") per the mock; sighted users get the severity from
// icon shape and color. SR users have neither, so the qualifier is what makes
// the tri-state distinguishable to them.
const stateSeverityHints: Record< EvidenceFieldState, string | null > = {
	provided: null,
	// translators: severity of a missing evidence field; surfaced only
	// to screen readers as the suffix to "Not provided".
	expected_missing: __( 'required', 'woocommerce-payments' ),
	// translators: severity of a missing evidence field; surfaced only
	// to screen readers as the suffix to "Not provided".
	optional_missing: __( 'optional', 'woocommerce-payments' ),
};

const stateIcons: Record< EvidenceFieldState, JSX.Element > = {
	provided: <Icon icon={ check } />,
	expected_missing: <Icon icon={ closeSmall } />,
	optional_missing: <Icon icon={ lineSolid } />,
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
						{ stateIcons[ state ] }
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
								{ stateLabels[ state ] }
							</VisuallyHidden>
						) : (
							<span className="dispute-outcome-evidence-list__state">
								<span aria-hidden="true">{ '— ' }</span>
								{ stateLabels[ state ] }
								<VisuallyHidden>
									{ ' ' + stateSeverityHints[ state ] }
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
