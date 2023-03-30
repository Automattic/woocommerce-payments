/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Loadable from '../loadable';

/**
 * SectionHeadingProps
 *
 * @typedef {Object} SectionHeadingProps
 *
 * @property {string} title       Section heading title.
 * @property {string} description Section heading description.
 * @property {boolean} [isLoading] Optional. Whether the section heading is loading.
 */
type SectionHeadingProps = {
	title: string;
	description: string | React.ReactNode;
	isLoading?: boolean;
};

/**
 * Renders the section heading component.
 *
 * @param {SectionHeadingProps} props Section heading props.
 * @param {string} props.title       Section heading title.
 * @param {string} props.description Section heading description.
 * @param {boolean} [props.isLoading] Optional. Whether the section heading should is loading.
 *
 * @return {JSX.Element} Rendered element with section heading.
 */
const DepositOverviewSectionHeading: React.FC< SectionHeadingProps > = ( {
	title,
	description,
	isLoading = false,
} ): JSX.Element => {
	return (
		<div className="wcpay-deposits-overview__heading">
			<span className="wcpay-deposits-overview__heading__title">
				<Loadable isLoading={ isLoading } value={ title } />
			</span>
			<div className="wcpay-deposits-overview__heading__description">
				<Loadable isLoading={ isLoading }>
					{ typeof description === 'string' ? (
						<span className="wcpay-deposits-overview__heading__description__text">
							{ description }
						</span>
					) : (
						<>{ description }</>
					) }
				</Loadable>
			</div>
		</div>
	);
};

export default DepositOverviewSectionHeading;
