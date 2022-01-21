<?php
// phpcs:ignoreFile - This is an auxiliary build tool, and not part of the plugin.

use Automattic\Jetpack\Changelog\Changelog;
use Automattic\Jetpack\Changelog\Parser;
use Automattic\Jetpack\Changelogger\FormatterPlugin;
use Automattic\Jetpack\Changelogger\PluginTrait;

/**
 * Jetpack Changelogger Formatter for WooCommerce Payments
 *
 * Class WCPayFormatter
 */
class WCPay_Changelog_Formatter extends Parser implements FormatterPlugin {
	use PluginTrait;

	/**
	 * Bullet for changes.
	 *
	 * @var string
	 */
	private $bullet = '*';

	/**
	 * Output date format.
	 *
	 * @var string
	 */
	private $date_format = 'Y-m-d';

	/**
	 * String used as the date for an unreleased version.
	 *
	 * @var string
	 */
	private $unreleased = '2022-xx-xx';

	/**
	 * Title for the changelog.
	 *
	 * @var string
	 */
	private $title = '*** WooCommerce Payments Changelog ***';

	/**
	 * Separator used in headings and change entries.
	 *
	 * @var string
	 */
	private $separator = '-';

	/**
	 * Modified version of parse() from KeepAChangelogParser.
	 *
	 * @param string $changelog Changelog contents.
	 * @return Changelog
	 * @throws InvalidArgumentException If the changelog data cannot be parsed.
	 */
	public function parse( $changelog ) {
		$ret = new Changelog();

		// Fix newlines and expand tabs.
		$changelog = strtr( $changelog, [ "\r\n" => "\n" ] );
		$changelog = strtr( $changelog, [ "\r" => "\n" ] );
		while ( strpos( $changelog, "\t" ) !== false ) {
			$changelog = preg_replace_callback(
				'/^([^\t\n]*)\t/m',
				function ( $m ) {
					return $m[1] . str_repeat( ' ', 4 - ( mb_strlen( $m[1] ) % 4 ) );
				},
				$changelog
			);
		}

		// Remove title. Check if the first line containing the defined title, and remove it.
		list( $first_line, $remaining ) = explode( "\n", $changelog, 2 );
		if ( false !== strpos( $first_line, $this->title ) ) {
			$changelog = $remaining;
		}

		// Entries make up the rest of the document.
		$entries = [];
		preg_match_all( '/^=\s+([^\n=]+)\s+=(((?!^=).)+)/ms', $changelog, $version_sections );

		foreach ( $version_sections[0] as $section ) {
			$heading_pattern = '/^= +(\[?[^] ]+\]?) - (.+?) =/';
			// Parse the heading and create a ChangelogEntry for it.
			preg_match( $heading_pattern, $section, $heading );
			if ( ! count( $heading ) ) {
				throw new InvalidArgumentException( "Invalid heading: $heading" );
			}

			$version   = $heading[1];
			$timestamp = $heading[2];
			if ( $timestamp === $this->unreleased ) {
				$timestamp       = null;
				$entry_timestamp = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			} else {
				try {
					$timestamp = new DateTime( $timestamp, new DateTimeZone( 'UTC' ) );
				} catch ( \Exception $ex ) {
					throw new InvalidArgumentException( "Heading has an invalid timestamp: $heading", 0, $ex );
				}
				if ( strtotime( $heading[2], 0 ) !== strtotime( $heading[2], 1000000000 ) ) {
					throw new InvalidArgumentException( "Heading has a relative timestamp: $heading" );
				}
				$entry_timestamp = $timestamp;
			}

			$entry = $this->newChangelogEntry(
				$version,
				array(
					'timestamp' => $timestamp,
				)
			);

			$entries[] = $entry;
			$content   = trim( preg_replace( $heading_pattern, '', $section ) );

			if ( '' === $content ) {
				// Huh, no changes.
				continue;
			}

			// Now parse all the subheadings and changes.
			while ( '' !== $content ) {
				$changes = [];
				$rows    = explode( "\n", $content );
				foreach ( $rows as $row ) {
					$row          = trim( $row );
					$row          = preg_replace( '/\\' . $this->bullet . '/', '', $row, 1 );
					$row_segments = explode( $this->separator, $row, 2 );
					array_push(
						$changes,
						[
							'subheading' => trim( $row_segments[0] ),
							'content'    => trim( $row_segments[1] ),
						]
					);
				}

				foreach ( $changes as $change ) {
					$entry->appendChange(
						$this->newChangeEntry(
							[
								'subheading' => $change['subheading'],
								'content'    => $change['content'],
								'timestamp'  => $entry_timestamp,
							]
						)
					);
				}
				$content = '';
			}
		}

		$ret->setEntries( $entries );

		return $ret;
	}

	/**
	 * Write a Changelog object to a string.
	 *
	 * @param Changelog $changelog Changelog object.
	 * @return string
	 */
	public function format( Changelog $changelog ) {
		$ret = '';

		foreach ( $changelog->getEntries() as $entry ) {
			$timestamp    = $entry->getTimestamp();
			$release_date = null === $timestamp ? $this->unreleased : $timestamp->format( $this->date_format );

			$ret .= '= ' . $entry->getVersion() . ' ' . $this->separator . ' ' . $release_date . " =\n";

			$prologue = trim( $entry->getPrologue() );
			if ( '' !== $prologue ) {
				$ret .= "\n$prologue\n\n";
			}

			foreach ( $entry->getChanges() as $change ) {
				$text = trim( $change->getContent() );
				if ( '' !== $text ) {
					$ret .= $this->bullet . ' ' . $change->getSubheading() . ' ' . $this->separator . ' ' . $text . "\n";
				}
			}

			$ret = trim( $ret ) . "\n\n";
		}

		$ret = $this->title . "\n\n" . trim( $ret ) . "\n";

		return $ret;
	}
}
