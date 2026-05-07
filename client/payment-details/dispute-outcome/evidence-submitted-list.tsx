/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
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
								<span aria-hidden="true">{ '— ' }</span>
								{ renderStateLabel( state ) }
							</span>
						) }
					</span>
				</li>
			) ) }
		</ul>
	);
};

export default EvidenceSubmittedList;
