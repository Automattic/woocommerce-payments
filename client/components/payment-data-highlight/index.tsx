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
import { Link } from '@woocommerce/components';
import { recordEvent } from 'tracks';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import './styles.scss';

const PaymentChangeFlow: React.FunctionComponent< {
	change: number;
} > = ( { change } ): JSX.Element => {
	return (
		<div
			className={ classNames( 'payment-data-highlight__amount-change', {
				'arrow-down': change < 0,
				'arrow-up': change >= 0,
			} ) }
		>
			<span className="payment-data-highlight__amount-change-icon">
				{ change < 0 && <ArrowDownIcon size={ 12 } /> }
				{ change >= 0 && <ArrowUpIcon size={ 12 } /> }
			</span>
			<span className="payment-data-highlight__amount-change-percentage">
				{ Math.abs( change ) }%
			</span>
		</div>
	);
};

const PaymentDataHighlight: React.FunctionComponent< {
	label: string;
	amount: string;
	change: number;
	reportUrl: string;
	tooltip?: string;
} > = ( { label, amount, change, reportUrl, tooltip } ): JSX.Element => {
	return (
		<div className="payment-data-highlight__wrapper">
			<div className="payment-data-highlight__visible">
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
			<div className="payment-data-highlight__view-report">
				<Link
					href={ reportUrl }
					onClick={ () =>
						recordEvent(
							'wcpay_overview_widget_data_highlight_view_report_click',
							{
								url: reportUrl,
							}
						)
					}
				>
					{ __( 'View report', 'woocommerce-paymnets' ) }
				</Link>
			</div>
		</div>
	);
};

export default PaymentDataHighlight;
