/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { EvidenceFieldStatus } from 'wcpay/disputes/new-evidence/types';
import type { RecommendationOutcome } from 'wcpay/disputes/new-evidence/recommendation-fields';

interface Props {
	fields: EvidenceFieldStatus[];
	outcome: RecommendationOutcome;
}

const headings: Record< RecommendationOutcome, string > = {
	could_help: __( 'What could help', 'woocommerce-payments' ),
	keep_doing: __( 'What to keep doing', 'woocommerce-payments' ),
};

// Placeholder copy until RiskOps (catkiinson) delivers final strings for
// RSM-1169 (could_help) and RSM-1170 (keep_doing). Intentionally not
// translated: throwaway strings would pollute .po files. When Catherine
// ships final copy, wrap with `__()` and drop the TODO markers.
const introCopy: Record< RecommendationOutcome, string > = {
	could_help:
		'TODO(catherine): RSM-1169 intro copy. Explains how the listed evidence types could have strengthened the response.',
	keep_doing:
		'TODO(catherine): RSM-1170 intro copy. Reinforces that the listed evidence types contributed to the win.',
};

const placeholderExplanation: Record< RecommendationOutcome, string > = {
	could_help: 'TODO(catherine): per-field explanation for the lost path.',
	keep_doing: 'TODO(catherine): per-field reinforcement for the won path.',
};

const RecommendationsList: React.FC< Props > = ( { fields, outcome } ) => {
	if ( fields.length === 0 ) {
		return null;
	}

	return (
		<>
			<CardDivider />
			<h3 className="dispute-outcome-view__section-heading">
				{ headings[ outcome ] }
			</h3>
			<p className="dispute-outcome-recommendations-list__intro">
				{ introCopy[ outcome ] }
			</p>
			<ul className="dispute-outcome-recommendations-list">
				{ fields.map( ( { key, label } ) => (
					<li
						key={ key }
						className="dispute-outcome-recommendations-list__item"
					>
						<span className="dispute-outcome-recommendations-list__label">
							{ label }
						</span>
						<span className="dispute-outcome-recommendations-list__placeholder">
							{ placeholderExplanation[ outcome ] }
						</span>
					</li>
				) ) }
			</ul>
		</>
	);
};

export default RecommendationsList;
