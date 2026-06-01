/**
 * External dependencies
 */
// eslint-disable-next-line import/no-unresolved
import { WordPressComponentProps } from '@wordpress/components/build-types/context/wordpress-component';
// eslint-disable-next-line no-restricted-syntax
import { Button } from '@wordpress/components';

export type AccordionProps = {
	/**
	 * The CSS class to apply to the wrapper element.
	 */
	className?: string;
	/**
	 * The content to display within the accordion.
	 */
	children: React.ReactNode;
	/**
	 * Ref to be forwarded to the root element
	 */
	ref?: React.Ref< HTMLDivElement >;
	/**
	 * Whether to use high density styling (smaller padding, no border radius).
	 *
	 * @default false
	 */
	highDensity?: boolean;
	/**
	 * Whether all accordion sections should be expanded by default.
	 *
	 * @default false
	 */
	defaultExpanded?: boolean;
};

export type AccordionRowProps = {
	/**
	 * The CSS class to apply to the wrapper element.
	 */
	className?: string;
	/**
	 * The content to display within the accordion row.
	 */
	children: React.ReactNode;
	/**
	 * Ref to be forwarded to the row element
	 */
	ref?: React.Ref< HTMLDivElement >;
};

export type AccordionBodyProps = {
	/**
	 * Props that are passed to the `Button` component in title within the
	 * `AccordionBody`.
	 *
	 * @default {}
	 */
	buttonProps?: Omit<
		WordPressComponentProps<
			Omit< Button.ButtonProps, 'icon' >,
			'button',
			false
		>,
		'size'
	>;
	/**
	 * The content to display in the `AccordionBody`.If a function is provided for
	 * this prop, it will receive an object with the `opened` prop as an argument.
	 */
	children?:
		| React.ReactNode
		| ( ( props: { opened: boolean } ) => React.ReactNode );

	/**
	 * The CSS class to apply to the wrapper element.
	 */
	className?: string;
	/**
	 * An icon to be shown next to the title.
	 */
	icon?: JSX.Element;
	/**
	 * Whether or not the accordion will start open.
	 */
	initialOpen?: boolean;
	/**
	 * A function that is called any time the component is toggled from its closed
	 * state to its opened state, or vice versa.
	 *
	 * @default noop
	 */
	onToggle?: ( next: boolean ) => void;
	/**
	 * When set to `true`, the component will remain open regardless of the
	 * `initialOpen` prop and the accordion will be prevented from being closed.
	 */
	opened?: boolean;
	/**
	 * Title text. It shows even when it is closed.
	 */
	title?: string;
	/**
	 * Scrolls the content into view when visible. This improves the UX when
	 * multiple `AccordionBody` components are stacked in a scrollable container.
	 *
	 * @default true
	 */
	scrollAfterOpen?: boolean;
	/**
	 * Optional subtitle text that appears below the title, rendered
	 * inside the title's toggle button. Plain string only — for
	 * subtitles that need to host interactive content (links,
	 * buttons), use `subtitleNode` instead.
	 */
	subtitle?: string;
	/**
	 * Optional subtitle content rendered as a sibling of the title's
	 * toggle button (not inside it). Use this when the subtitle needs
	 * to host interactive content like links, which can't be nested
	 * inside the toggle button. Mutually exclusive with `subtitle`;
	 * when set, `subtitle` is ignored.
	 *
	 * Visible only when the accordion is expanded — `subtitleNode` is
	 * treated as body content, not header chrome. Use the plain
	 * `subtitle` prop when the text must persist in the collapsed
	 * state.
	 */
	subtitleNode?: React.ReactNode;
	/**
	 * Ref to be forwarded to the body element
	 */
	ref?: React.Ref< HTMLDivElement >;
	/**
	 * Whether to use large title size (15px).
	 *
	 * @default false
	 */
	lg?: boolean;
	/**
	 * Whether to use medium title size (13px).
	 *
	 * @default true
	 */
	md?: boolean;
};

export type AccordionTitleProps = Omit< Button.ButtonProps, 'icon' > & {
	/**
	 * An icon to be shown next to the title.
	 */
	icon?: JSX.Element;
	/**
	 * Whether or not the `AccordionBody` is currently opened or not.
	 */
	isOpened?: boolean;
	/**
	 * The title text.
	 */
	title?: string;
	/**
	 * Optional subtitle text that appears below the title.
	 */
	subtitle?: string;
	/**
	 * Whether to use large title size (15px).
	 *
	 * @default false
	 */
	lg?: boolean;
	/**
	 * Whether to use medium title size (13px).
	 *
	 * @default true
	 */
	md?: boolean;
	/**
	 * Ref to be forwarded to the title button element
	 */
	ref?: React.LegacyRef< HTMLButtonElement >;
};

export type AccordionSubtitleProps = {
	/**
	 * The content to display within the subtitle.
	 */
	children?: React.ReactNode;
	/**
	 * Ref to be forwarded to the subtitle element
	 */
	ref?: React.Ref< HTMLDivElement >;
};
