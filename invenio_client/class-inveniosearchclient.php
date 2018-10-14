<?php

class InvenioSearchClient extends GuzzleHttp\Client {

	var $uri;
	var $timeout;

	function __construct( $uri, $timeout = 2.0 ) {
		parent::__construct( [
			'base_uri' => $uri,
			'timeout'  => $timeout,
		]);
		$this->uri     = $uri;
		$this->timeout = $timeout;
	}

	function set_uri( $uri ) {
		$this->uri = $uri;
	}

	function get_uri() {
		return $this->uri;
	}

	function set_timeout( $timeout ) {
		$this->timeout = $timeout;
	}

	function get_timeout() {
		return $this->timeout;
	}

	function search( $args ) {
		$file_name = plugin_dir_path( __FILE__ ) . 'records.json';
		$check     = file_exists( $file_name ) && filemtime( $file_name ) >= time() - 3600 && filesize( $file_name ) > 0;
		if ( $check ) {
			error_log( 'Retreiving records from file. ', 0 );
			return $this->records_from_file( $file_name );
		} else {
			try {
				$response = $this->request( 'GET', 'search', $args );
				if ( $response->getStatusCode() === 200 ) {
					$this->update_file( $file_name, $response->getBody() );
					return json_decode( $response->getBody(), true );
				}
			} catch ( Exception $e ) {
				error_log( 'Error retreiving records: ' . $e->getMessage(), 0 );
				return ( file_exists( $file_name ) && filesize( $file_name ) > 0 ) ? $this->records_from_file( $file_name ) : '';
			}
		}

	}

	private function update_file( $file_name, $records ) {
		$fp = fopen( $file_name, 'w' );
		fwrite( $fp, $records );
		fclose( $fp );
	}

	private function records_from_file( $file_name ) {
		$string = file_get_contents( $file_name );
		return json_decode( $string, true );
	}
};
