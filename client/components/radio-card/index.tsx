/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface Props {
	name: string;
	selected: string;
	options: {
		label: string;
		value: string;
		icon: React.ReactNode;
		content: React.ReactNode;
	}[];
	onChange: ( value: string ) => void;
	className?: string;
}
const RadioCard: React.FC< Props > = ( {
	name,
	selected,
	options,
	onChange,
	className,
} ) => {
	return (
		<>
			{ options.map( ( { label, icon, value, content } ) => {
				const id = `radio-card-${ name }-${ value }`;
				const checked = value === selected;
				const handleChange = () => onChange( value );

				return (
					<div
						role="radio"
						aria-checked={ checked }
						tabIndex={ 0 }
						key={ value }
						onClick={ handleChange }
						onKeyDown={ ( event ) => {
							if ( event.key === 'Enter' ) handleChange();
						} }
						className={ classNames(
							'wcpay-component-radio-card',
							{ checked },
							className
						) }
					>
						<div className="wcpay-component-radio-card__label">
							<input
								id={ id }
								type="radio"
								name={ name }
								value={ value }
								checked={ !! checked }
								onChange={ handleChange }
								tabIndex={ -1 }
							/>
							<label htmlFor={ id }>{ label }</label>
							{ icon }
						</div>
						{ checked && content }
					</div>
				);
			} ) }
		</>
	);
};

export default RadioCard;
