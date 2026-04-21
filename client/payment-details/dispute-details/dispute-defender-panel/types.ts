/** @format */

export type GapResolution = 'provide' | 'substituted';

export interface DefenseDraftMeta {
	model: string;
	prompt_version: string;
	generated_at: string;
}

export interface DefenseGap {
	id: string;
	label: string;
	default_resolution: GapResolution;
	sentences: {
		with_evidence: string;
		substituted: string;
	};
}

export interface DefenseDraft {
	narrative: string;
	gaps: DefenseGap[];
	meta: DefenseDraftMeta;
}
