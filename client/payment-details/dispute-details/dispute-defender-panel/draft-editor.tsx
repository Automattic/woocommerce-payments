/** @format */

/**
 * External dependencies
 */
import React from 'react';
import {
	Button,
	Notice,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { DefenseGap, GapResolution } from './types';

interface DraftEditorProps {
	narrative: string;
	onNarrativeChange: ( value: string ) => void;
	gaps: DefenseGap[];
	resolutions: Record< string, GapResolution >;
	onResolutionChange: ( gap: DefenseGap, next: GapResolution ) => void;
	orphanWarning: boolean;
	onDismissOrphanWarning: () => void;
	clipboardNotice: boolean;
	onDismissClipboardNotice: () => void;
	onUseDraft: () => void;
	isBusy: boolean;
}

/**
 * Narrative editor + gap toggle list + "Use this draft" action. Kept as a
 * pure sibling component so the parent panel stays focused on data fetching
 * and state wiring.
 */
const DraftEditor: React.FC< DraftEditorProps > = ( {
	narrative,
	onNarrativeChange,
	gaps,
	resolutions,
	onResolutionChange,
	orphanWarning,
	onDismissOrphanWarning,
	clipboardNotice,
	onDismissClipboardNotice,
	onUseDraft,
	isBusy,
} ) => {
	return (
		<div className="dispute-defender-panel__editor">
			<TextareaControl
				__nextHasNoMarginBottom
				className="dispute-defender-panel__narrative"
				label={ __( 'Narrative', 'woocommerce-payments' ) }
				help={ __(
					'Review and edit the draft. Toggle individual pieces of evidence below if you can\u2019t provide them.',
					'woocommerce-payments'
				) }
				value={ narrative }
				onChange={ onNarrativeChange }
				rows={ 10 }
			/>
			{ orphanWarning && (
				<Notice
					status="warning"
					isDismissible={ true }
					onRemove={ onDismissOrphanWarning }
					className="dispute-defender-panel__orphan-warning"
				>
					{ __(
						'We couldn\u2019t find the original sentence. The replacement was appended \u2014 please review.',
						'woocommerce-payments'
					) }
				</Notice>
			) }
			{ gaps.length > 0 && (
				<ul className="dispute-defender-panel__gaps">
					{ gaps.map( ( gap ) => (
						<li
							key={ gap.id }
							className="dispute-defender-panel__gap"
						>
							<RadioControl
								label={ gap.label }
								selected={ resolutions[ gap.id ] || 'provide' }
								options={ [
									{
										label: __(
											'Provide',
											'woocommerce-payments'
										),
										value: 'provide',
									},
									{
										label: __(
											'Can\u2019t provide',
											'woocommerce-payments'
										),
										value: 'substituted',
									},
								] }
								onChange={ ( value: string ) =>
									onResolutionChange(
										gap,
										value as GapResolution
									)
								}
							/>
						</li>
					) ) }
				</ul>
			) }
			<div className="dispute-defender-panel__actions">
				<Button
					variant="secondary"
					onClick={ onUseDraft }
					disabled={ isBusy }
				>
					{ __( 'Use this draft', 'woocommerce-payments' ) }
				</Button>
			</div>
			{ clipboardNotice && (
				<Notice
					status="success"
					isDismissible={ true }
					onRemove={ onDismissClipboardNotice }
					className="dispute-defender-panel__clipboard-notice"
				>
					{ __(
						'Draft copied to clipboard.',
						'woocommerce-payments'
					) }
				</Notice>
			) }
		</div>
	);
};

export default DraftEditor;
