<?php

namespace CirrusSearch\Maintenance;

use \CirrusSearch\Connection;
use \CirrusSearch\ElasticsearchIntermediary;
use \CirrusSearch\Util;

use Elastica;
use Elastica\Index;
use Elastica\Query;
use Elastica\Type;
use Elastica\Filter;

/**
 * Dump an index to stdout
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );
require_once( __DIR__ . '/../includes/Maintenance/Maintenance.php' );

/**
 * Dump an index from elasticsearch.
 */
class DumpIndex extends Maintenance {

	private $indexType;
	private $indexBaseName;
	private $indexIdentifier;

	/**
	 * @var int number of docs per shard we export
	 */
	private $inputChunkSize = 500;

	/**
	 * @var boolean
	 */
	private $logToStderr = false;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Dump an index into a 'json' based format stdout. " .
			"This format complies to the elasticsearch bulk format and can be directly used " .
			"with a curl command like : " .
			"curl -s -XPOST localhost:9200/{index}/_bulk --data-binary @dump-file\n" .
			"Note that you need to specify the index in the URL because the bulk commands do not " .
			"contain the index name. Beware that the bulk import is not meant to import very large " .
			"files, sweetspot seems to be between 2000 and 5000 documents (see examples below).".
			"\n\nExamples :\n" .
			" - Dump a general index :" .
			"\n\tdumpIndex --indexType general\n" .
			" - Dump a large content index into compressed chunks of 100000 documents :" .
			"\n\tdumpIndex --indexType content | split -d -a 9 -l 100000  --filter 'gzip -c > \$FILE.txt.gz' - \"\" \n" .
			"\nYou can import the data with the following commands :\n" .
			" - Import chunks of 2000 documents :" .
			"\n\tcat dump | split -l 4000 --filter 'curl -s http://elastic:9200/{indexName}/_bulk --data-binarya @- > /dev/null'\n" .
			" - Import 3 chunks of 2000 documents in parallel :" .
			"\n\tcat dump | parallel --pipe -L 2 -N 2000 -j3 'curl -s http://elastic:9200/{indexName}/_bulk --data-binary @- > /dev/null'");
		$this->addOption( 'indexType', 'Index to dump. Either content or general.', true, true );
		$this->addOption( 'baseName', 'What basename to use, ' .
			'defaults to wiki id.', false, true );
		$this->addOption( 'filter', 'Dump only the documents that match the filter query ' .
			'(queryString syntax).', false, true );
		$this->addOption( 'limit', 'Maximum number of documents to dump, 0 means no limit. Defaults to 0.', false, true );
		$this->addOption( 'indexIdentifier', 'Force the index identifier, use the alias otherwise.', false, true );
		$this->addOption( 'escapeUnicode', 'Escape unicode', false, false );
	}

	public function execute() {
		global $wgPoolCounterConf;

		// Make sure we don't flood the pool counter
		unset( $wgPoolCounterConf['CirrusSearch-Search'] );
		// Set the timeout for maintenance actions
		$this->setConnectionTimeout();

		$this->indexType = $this->getOption( 'indexType' );
		$this->indexBaseName = $this->getOption( 'baseName', wfWikiId() );

		$indexTypes = $this->getAllIndexTypes();
		if ( !in_array( $this->indexType, $indexTypes ) ) {
			$this->error( 'indexType option must be one of ' .
				implode( ', ', $indexTypes ), 1 );
		}

		$utils = new ConfigUtils( $this->getClient(), $this );

		$this->indexIdentifier = $this->getOption( 'indexIdentifier' );

		$filter = null;
		if( $this->hasOption( 'filter' ) ) {
			$filter = new Elastica\Filter\Query(
				new Elastica\Query\QueryString( $this->getOption( 'filter' ) ) );
		}

		$limit = (int) $this->getOption( 'limit', 0 );

		$query = new Query();
		$query->setFields( array( '_id', '_type', '_source' ) );
		if( $filter ) {
			$query->setQuery( new \Elastica\Query\Filtered(
				new \Elastica\Query\MatchAll(), $filter ) );
		}

		$scrollOptions = array(
			'search_type' => 'scan',
			'scroll' => "15m",
			'size' => $this->inputChunkSize
		);
		$index = $this->getIndex();

		$result = $index->search( $query, $scrollOptions );

		$totalDocsInIndex = $result->getResponse()->getData();
		$totalDocsInIndex = $totalDocsInIndex['hits']['total'];
		$totalDocsToDump = $limit > 0 ? $limit : $totalDocsInIndex;
		$docsDumped = 0;

		$this->logToStderr = true;
		$this->output( "Dumping $totalDocsToDump documents ($totalDocsInIndex in the index)\n" );

		$self = $this;
		Util::iterateOverScroll( $index, $result->getResponse()->getScrollId(), '15m',
			function( $results ) use ( $self, &$docsDumped, $totalDocsToDump ) {
				foreach ( $results as $result ) {
					$document = array(
						'_id' => $result->getId(),
						'_type' => $result->getType(),
						'_source' => $result->getSource()
					);
					$self->write( $document );
					$docsDumped++;
					$self->outputProgress( $docsDumped, $totalDocsToDump );
				}
			}, $limit, 5 );
		$this->output( "Dump done.\n" );
	}

	private function setConnectionTimeout() {
		global $wgCirrusSearchMaintenanceTimeout;
		Connection::setTimeout( $wgCirrusSearchMaintenanceTimeout );
	}

	public function write( array $document ) {
		$indexOp = array (
			'index' => array (
				'_type' => $document['_type'],
				'_id' => $document['_id']
			) );

		$this->writeLine( json_encode( $indexOp ) );
		if ( $this->hasOption( 'escapeUnicode' ) ) {
			$this->writeLine( json_encode( $document['_source'] ) );
		} else {
			$this->writeLine( json_encode( $document['_source'], JSON_UNESCAPED_UNICODE ) );
		}
	}

	private function writeLine( $data ) {
		if( !fwrite( STDOUT, $data  . "\n" ) ) {
			throw new IndexDumperException( "Cannot write to standard output" );
		}
	}

	/**
	 * @return Elastica\Client
	 */
	private function getClient() {
		return Connection::getClient();
	}

	/**
	 * @return Elastica\Index being updated
	 */
	private function getIndex() {
		if ( $this->indexIdentifier ) {
			return Connection::getIndex( $this->indexBaseName, $this->indexType, $this->indexIdentifier );
		} else {
			return Connection::getIndex( $this->indexBaseName, $this->indexType );
		}
	}

	/**
	 * @return array
	 */
	private function getAllIndexTypes() {
		return Connection::getAllIndexTypes();
	}

	public function outputIndented( $message ) {
		$this->output( "\t$message" );
	}

	public function output( $message, $channel = NULL ) {
		if ( $this->mQuiet ) {
			return;
		}
		if ( $this->logToStderr ) {
			// We must log to stderr
			fwrite ( STDERR, $message );
		} else {
			parent::output( $message );
		}
	}

	private function outputProgress( $docsDumped, $limit ) {
		if( $docsDumped <= 0 ) {
			return;
		}
		$pctDone = (int) ( ( $docsDumped / $limit ) * 100 );
		if( ( $pctDone % 2 ) == 0 ) {
			$this->outputIndented( "$pctDone% done...\n" );
		}
	}
}

/**
 * An error that occurred while writing to the dump
 */
class IndexDumperException extends \Exception {
}

$maintClass = "CirrusSearch\Maintenance\DumpIndex";
require_once RUN_MAINTENANCE_IF_MAIN;