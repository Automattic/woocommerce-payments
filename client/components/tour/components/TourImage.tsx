/**
 * External dependencies
 */
import classnames from 'classnames';
import React, { ImgHTMLAttributes } from 'react';

/**
 * Internal dependencies
 */

interface TourImageProps extends ImgHTMLAttributes< HTMLImageElement > {
	mobileOnly?: boolean;
}

const TourImage: React.FC< TourImageProps > = ( {
	alt,
	className,
	mobileOnly,
	...props
} ) => {
	return (
		<img
			{ ...props }
			alt={ alt }
			width={ 350 }
			height={ 204 }
			className={ classnames( 'tour-modal__image', className, {
				'tour-modal__image--mobile': mobileOnly,
			} ) }
		/>
	);
};

export default TourImage;
