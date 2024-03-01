/** @format */
/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import ArrowUpIcon from 'gridicons/dist/arrow-up';
import ArrowDownIcon from 'gridicons/dist/arrow-down';
import { __ } from '@wordpress/i18n';
import { VisuallyHidden } from '@wordpress/components';
/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import './styles.scss';

const PaymentChangeFlow: React.FunctionComponent< {
	change: number;
} > = ( { change } ): JSX.Element => {
	if ( change < 0 ) {
		return (
			<div className="payment-data-highlight__amount-change-arrow-down">
				<span className="payment-data-highlight__amount-change-arrow-down-icon">
					<ArrowDownIcon size={ 12 } />
				</span>
				<span className="payment-data-highlight__amount-change-percentage">
					{ Math.abs( change ) }%
				</span>
			</div>
		);
	}

	return (
		<div className="payment-data-highlight__amount-change-arrow-up">
			<span className="payment-data-highlight__amount-change-arrow-up-icon">
				<ArrowUpIcon size={ 12 } />
			</span>
			<span className="payment-data-highlight__amount-change-arrow-up-percentage">
				{ Math.abs( change ) }%
			</span>
		</div>
	);
};

const PaymentDataHighlight: React.FunctionComponent< {
	label: string;
	amount: string;
	change: number;
	tooltip?: string;
} > = ( { label, amount, change, tooltip } ): JSX.Element => {
	return (
		<div className="payment-data-highlight__wrapper">
			<div className="payment-data-highlight__label">
				<span>{ label }</span>

				{ tooltip && (
					<ClickTooltip
						buttonIcon={ <HelpOutlineIcon /> }
						isVisible={ false }
						content={ tooltip }
					>
						<VisuallyHidden>{ tooltip }</VisuallyHidden>
					</ClickTooltip>
				) }
			</div>
			<div className="payment-data-highlight__amount">
				<div className="payment-data-highlight__amount-number">
					{ amount }
				</div>
				<div className="payment-data-highlight__amount-change">
					<PaymentChangeFlow change={ change } />
				</div>
			</div>
		</div>
	);
};

export default PaymentDataHighlight;
