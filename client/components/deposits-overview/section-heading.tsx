/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import Loadable from 'components/loadable';

interface HeadingProps {
	title: string;
	desc: string;
	isLoading?: boolean;
}

/**
 * Renders a Deposits Overview Section Heading.
 *
 * Used in the Deposits Overview component to render Next Deposits and Deposits History section headings.
 *
 * @param {HeadingProps} props Section heading props { title, desc, isLoading (optional) }
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsOverviewSectionHeading: React.FC< HeadingProps > = ( {
	title,
	desc,
	isLoading,
}: HeadingProps ): JSX.Element => {
	// Set default value for isLoading.
	isLoading = isLoading || false;

	return (
		<div className="wcpay-deposits-overview-section__header">
			<p className="wcpay-deposits-overview-section__header__title">
				<Loadable
					isLoading={ isLoading }
					display="inline"
					placeholder={ title }
					value={ title }
				/>
			</p>

			<p className="wcpay-deposits-overview-section__header__description">
				<Loadable
					isLoading={ isLoading }
					display="inline"
					placeholder={ desc }
					value={ desc }
				/>
			</p>
		</div>
	);
};

export default DepositsOverviewSectionHeading;
