<?php
/**
Plugin Name: Invenio search - Presents Invenio search results.
Description: Presents Invenio search results in worpress page.
Version: 1.0.1
Author: Torrisi Mario
Author URI: https://twitter.com/__mtorrisi__
License: GPLv2+
Text Domain: invenio-search
**/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once 'vendor/autoload.php';
include_once 'invenio_client/class-inveniosearchclient.php';

function last_uploaded_func( $atts, $is_client = null ) {
	$is_client = ( $is_client ) ? $is_client : new InvenioSearchClient( get_option( 'invenio_url' ), get_option( 'timeout' ) );
	$query     = [
		'query' => [
			'rg' => get_option( 'rec_number' ),
			'sf' => 'recid',
			'so' => 'd',
			'ot' => 'abstract,title,authors,creation_date,recid,doi,collection',
			'of' => 'recjson',
		],
	];

	$response = $is_client->search( $query );

	if ( empty( $response ) ) {
		return '<strong>' . __( 'No records to show, maybe an error contacting the server occurs.', 'invenio-search' ) . '</strong>';
	}
	$return = '<ul class="repository">';

	if ( is_array( $response ) ) {
		foreach ( $response as $record ) {
			$rec_title    = $record['title'];
			$rec_abstract = $record['abstract'];
			$rec_collecti = $record['collection'];
			$rec_authors  = $record['authors'];
			$txt_authors  = '';
			foreach ( $rec_authors as $author ) {
				$txt_authors .= esc_html( $author['full_name'] . ', ' );
			}
			$rec_doi = $record['doi'];
			$rec_url = $is_client->get_uri() . '/record/' . $record['recid'];
			$return .= "<li>
				<a href='" . esc_url( $rec_url ) . "' target='_blank'>
					<strong>" . esc_html( $rec_title['title'] ) . '</strong>
				</a><p><i>' . $txt_authors . '</i></p><p>' . esc_html( $rec_abstract['summary'] ) . '<p/>
				<em>' . esc_html( $rec_collecti['primary'] ) . '</em>
				<span> [' . esc_html( $rec_doi ) . '] </span></p><hr /></li>';
		}
	} else {
		$return .= '<li>' . esc_html( "$response", 'text_domain' ) . '</li>';
	}

	// Don't forget to close the list
	$return .= '</ul>';

	return $return;
}

add_shortcode( 'last_uploaded', 'last_uploaded_func' );

// Register the menu.
add_action( 'admin_menu', 'is_plugin_menu_func' );
function is_plugin_menu_func() {
	add_submenu_page( 'options-general.php',  // Which menu parent
		'Invenio search',            // Page title
		'Invenio search',            // Menu title
		'manage_options',       // Minimum capability (manage_options is an easy way to target administrators)
		'invenio_search',            // Menu slug
		'is_plugin_options'     // Callback that prints the markup
	);
}

// Print the markup for the page
function is_plugin_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	if ( 'success' == isset( $_GET['status'] ) && $_GET['status'] ) {
		?>
		<div id="message" class="updated notice is-dismissible">
			<p><?php esc_html_e( 'Settings updated!', 'invenio-search' ); ?></p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'invenio-search' ); ?>
				</span>
			</button>
		</div>
		<?php
	}
	?>
	<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
		<h3><?php esc_html_e( 'Invenio Repository Info', 'invenio-search' ); ?></h3>
		<table>
			<tr>
				<th scope="row">
					<label for="invenio_url"><?php esc_html_e( 'Invenio Repository (URL)', 'invenio-search' ); ?></label>
				</th>
				<td>
					<input class="regular-text code" type="text" name="invenio_url" value="<?php echo esc_html( get_option( 'invenio_url' ) ); ?>" placeholder="http://your-repo.url" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rec_number"><?php esc_html_e( 'Returned record', 'invenio-search' ); ?></label>
				</th>
				<td>
					<input class="" type="number" min="1" max="10" name="rec_number" value="<?php echo esc_html( get_option( 'rec_number', 5 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="timeout"><?php esc_html_e( 'Timeout', 'invenio-search' ); ?></label>
				</th>
				<td>
					<input class="" type="number" min="1" max="10" name="timeout" value="<?php echo esc_html( get_option( 'timeout', 5 ) ); ?>" />
				</td>
			</tr>
		</table>
		<input type="hidden" name="action" value="update_invenio_search_settings" />
		<input class="button button-primary" type="submit" value="<?php esc_html_e( 'Save', 'invenio-search' ); ?>" />

	</form>

	<?php
}

add_action( 'admin_post_update_invenio_search_settings', 'invenio_search_handle_save' );
function invenio_search_handle_save() {
	// Get the options that were sent
	$invenio_url = ( ! empty( $_POST['invenio_url'] ) ) ? $_POST['invenio_url'] : null;
	$timeout     = ( ! empty( $_POST['timeout'] ) ) ? $_POST['timeout'] : null;
	$records     = ( ! empty( $_POST['rec_number'] ) ) ? $_POST['rec_number'] : null;
	// Validation would go here
	// Update the values
	update_option( 'invenio_url', $invenio_url, true );
	update_option( 'timeout', $timeout, true );
	update_option( 'rec_number', $records, true );
	$file_name = plugin_dir_path( __FILE__ ) . 'invenio_client/records.json';
	$fp        = fopen( $file_name, 'w' );
	fclose( $fp );

	// Redirect back to settings page
	// The ?page=... corresponds to the "slug"
	// set in the fourth parameter of add_submenu_page() above.
	$redirect_url = get_bloginfo( 'url' ) . '/wp-admin/options-general.php?page=invenio_search&status=success';
	header( 'Location: ' . $redirect_url );
	exit;
}
