/** @format */
/**
 * Internal dependencies
 */
import './style.scss';

const ProgressBar = ( { progressLabel, totalLabel, progress } ) => {
	const useInnerLabel = 0.5 < progress;
	return (
		<div className="progressbar">
			<div className="progressbar__container">
				<div
					className="progressbar__inner"
					style={ { width: progress * 100 + '%' } }
				>
					{ useInnerLabel && (
						<span className="progressbar__inner-progress-label">
							{ progressLabel }
						</span>
					) }
				</div>
				{ ! useInnerLabel && (
					<span className="progressbar__outer-progress-label">
						{ progressLabel }
					</span>
				) }
			</div>
			<span className="progressbar__total-label">{ totalLabel }</span>
		</div>
	);
};

export default ProgressBar;
