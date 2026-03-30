<?php
/*
Plugin Name: YOURLS to PeakURL
Plugin URI: https://github.com/PeakURL/YOURLS-to-PeakURL
Description: Export YOURLS links as PeakURL-ready CSV, JSON, or XML for the PeakURL Bulk Import tool.
Version: 1.0.0
Author: PeakURL
Author URI: https://peakurl.org
*/

if ( ! defined( 'YOURLS_ABSPATH' ) ) {
	die();
}

define( 'PEAKURL_YOURLS_EXPORT_SLUG', 'peakurl-export' );
define( 'PEAKURL_YOURLS_EXPORT_VERSION', '1.0.0' );
define( 'PEAKURL_YOURLS_EXPORT_NONCE_ACTION', 'peakurl_export_links' );

yourls_add_action( 'plugins_loaded', 'peakurl_yourls_register_admin_page' );
yourls_add_action( 'load-' . PEAKURL_YOURLS_EXPORT_SLUG, 'peakurl_yourls_handle_download' );

/**
 * Register the plugin administration page.
 *
 * @return void
 */
function peakurl_yourls_register_admin_page() {
	yourls_register_plugin_page(
		PEAKURL_YOURLS_EXPORT_SLUG,
		'PeakURL Export',
		'peakurl_yourls_render_admin_page'
	);
}

/**
 * Handle export downloads before YOURLS renders the admin page wrapper.
 *
 * @return void
 */
function peakurl_yourls_handle_download() {
	$action = isset( $_GET['action'] ) ? trim( (string) $_GET['action'] ) : '';
	if ( 'download' !== $action ) {
		return;
	}

	$format = isset( $_GET['format'] ) ? strtolower( trim( (string) $_GET['format'] ) ) : 'csv';
	if ( ! in_array( $format, [ 'csv', 'json', 'xml' ], true ) ) {
		yourls_die( 'Unsupported export format.', 'Invalid export format', 400 );
	}

	yourls_verify_nonce( PEAKURL_YOURLS_EXPORT_NONCE_ACTION, $_REQUEST['nonce'] ?? '' );
	peakurl_yourls_stream_export( $format );
	exit;
}

/**
 * Render the plugin admin page.
 *
 * @return void
 */
function peakurl_yourls_render_admin_page() {
	$stats       = yourls_get_db_stats();
	$total_links = isset( $stats['total_links'] ) ? (int) $stats['total_links'] : 0;
	$base_url    = yourls_admin_url( 'plugins.php?page=' . PEAKURL_YOURLS_EXPORT_SLUG . '&action=download' );
	$csv_url     = yourls_nonce_url( PEAKURL_YOURLS_EXPORT_NONCE_ACTION, $base_url . '&format=csv' );
	$json_url    = yourls_nonce_url( PEAKURL_YOURLS_EXPORT_NONCE_ACTION, $base_url . '&format=json' );
	$xml_url     = yourls_nonce_url( PEAKURL_YOURLS_EXPORT_NONCE_ACTION, $base_url . '&format=xml' );
	?>
	<h2>Export Links for PeakURL</h2>
	<p>
		Export your YOURLS links into a PeakURL-compatible import file. PeakURL's Bulk Import accepts CSV, JSON,
		and XML uploads, and this plugin generates all three formats from the current YOURLS URL table.
	</p>
	<p>
		<strong><?php echo (int) $total_links; ?></strong> links are currently available for export.
	</p>
	<p>
		The CSV export is ready for direct upload into PeakURL and uses these columns:
		<code>url,alias,title,password,expires</code>.
		Password and expiry fields are included as blank columns because YOURLS core does not store them.
	</p>
	<ul>
		<li><a class="button button-primary" href="<?php echo htmlspecialchars( $csv_url, ENT_QUOTES, 'UTF-8' ); ?>">Download CSV for PeakURL</a></li>
		<li><a class="button" href="<?php echo htmlspecialchars( $json_url, ENT_QUOTES, 'UTF-8' ); ?>">Download JSON</a></li>
		<li><a class="button" href="<?php echo htmlspecialchars( $xml_url, ENT_QUOTES, 'UTF-8' ); ?>">Download XML</a></li>
	</ul>
	<p>
		In PeakURL, open <strong>Dashboard -&gt; Bulk Import -&gt; File Upload</strong> and upload the exported file.
	</p>
	<?php
}

/**
 * Stream the selected export format.
 *
 * @param string $format Export format.
 * @return void
 */
function peakurl_yourls_stream_export( $format ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	yourls_no_cache_headers();

	$filename = sprintf( 'peakurl-import-%s.%s', gmdate( 'Ymd-His' ), $format );

	if ( 'csv' === $format ) {
		yourls_content_type_header( 'text/csv' );
	} elseif ( 'json' === $format ) {
		yourls_content_type_header( 'application/json' );
	} else {
		yourls_content_type_header( 'application/xml' );
	}

	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	if ( 'csv' === $format ) {
		peakurl_yourls_stream_csv();
		return;
	}

	if ( 'json' === $format ) {
		peakurl_yourls_stream_json();
		return;
	}

	peakurl_yourls_stream_xml();
}

/**
 * Stream CSV output.
 *
 * @return void
 */
function peakurl_yourls_stream_csv() {
	$output = fopen( 'php://output', 'wb' );
	if ( false === $output ) {
		yourls_die( 'Could not open the export stream.', 'Export failed', 500 );
	}

	fputcsv( $output, [ 'url', 'alias', 'title', 'password', 'expires' ] );

	peakurl_yourls_walk_records(
		static function ( $record ) use ( $output ) {
			fputcsv(
				$output,
				[
					$record['url'],
					$record['alias'],
					$record['title'],
					'',
					'',
				]
			);
		}
	);

	fclose( $output );
}

/**
 * Stream JSON output.
 *
 * @return void
 */
function peakurl_yourls_stream_json() {
	echo "[\n";

	$first = true;
	peakurl_yourls_walk_records(
		static function ( $record ) use ( &$first ) {
			$item = [
				'destinationUrl' => $record['url'],
				'alias'          => $record['alias'],
				'title'          => $record['title'],
				'password'       => '',
				'expiresAt'      => '',
			];

			if ( ! $first ) {
				echo ",\n";
			}

			echo json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$first = false;
		}
	);

	echo "\n]\n";
}

/**
 * Stream XML output.
 *
 * @return void
 */
function peakurl_yourls_stream_xml() {
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<urls>\n";

	peakurl_yourls_walk_records(
		static function ( $record ) {
			echo "  <url>\n";
			echo '    <destinationUrl>' . peakurl_yourls_xml_escape( $record['url'] ) . "</destinationUrl>\n";
			echo '    <alias>' . peakurl_yourls_xml_escape( $record['alias'] ) . "</alias>\n";
			echo '    <title>' . peakurl_yourls_xml_escape( $record['title'] ) . "</title>\n";
			echo "    <password></password>\n";
			echo "    <expiresAt></expiresAt>\n";
			echo "  </url>\n";
		}
	);

	echo "</urls>\n";
}

/**
 * Iterate through YOURLS link rows in batches and hand them to a callback.
 *
 * @param callable $callback Callback to receive each normalized record.
 * @return void
 */
function peakurl_yourls_walk_records( $callback ) {
	$offset = 0;
	$limit  = 500;
	$table  = YOURLS_DB_TABLE_URL;
	$db     = yourls_get_db( 'read-peakurl_export_links' );

	do {
		$rows = $db->fetchObjects(
			"SELECT `keyword`, `url`, `title`, `timestamp`, `clicks` FROM `$table` ORDER BY `timestamp` ASC, `keyword` ASC LIMIT $offset, $limit"
		);

		if ( empty( $rows ) ) {
			break;
		}

		foreach ( $rows as $row ) {
			$record = [
				'alias'     => isset( $row->keyword ) ? (string) $row->keyword : '',
				'url'       => isset( $row->url ) ? (string) $row->url : '',
				'title'     => isset( $row->title ) ? (string) $row->title : '',
				'timestamp' => isset( $row->timestamp ) ? (string) $row->timestamp : '',
				'clicks'    => isset( $row->clicks ) ? (int) $row->clicks : 0,
			];

			$callback( $record );
		}

		$offset += count( $rows );
	} while ( count( $rows ) === $limit );
}

/**
 * Escape a value for XML element output.
 *
 * @param string $value Raw value.
 * @return string
 */
function peakurl_yourls_xml_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
}
