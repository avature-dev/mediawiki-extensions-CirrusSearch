<?php

namespace CirrusSearch\Maintenance;

use \CirrusSearch\Connection;
use \CirrusSearch\ElasticsearchIntermediary;
use Elastica;

/**
 * Update the search configuration on the search backend.
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
 * Update the elasticsearch configuration for this index.
 */
class UpdateOneSearchIndexConfig extends Maintenance {
	private $indexType;

	// Are we going to blow the index away and start from scratch?
	private $startOver;

	private $reindexChunkSize;
	private $reindexRetryAttempts;

	private $indexBaseName;
	private $indexIdentifier;
	private $reindexAndRemoveOk;

	/**
	 * @var boolean are there too few replicas in the index we're making?
	 */
	private $tooFewReplicas = false;

	/**
	 * @var int number of processes to use when reindexing
	 */
	private $reindexProcesses;

	/**
	 * @var string language code we're building for
	 */
	private $langCode;

	/**
	 * @var bool prefix search on any term
	 */
	private $prefixSearchStartsWithAny;

	/**
	 * @var bool use suggestions on text fields
	 */
	private $phraseSuggestUseText;

	/**
	 * @var bool print config as it is being checked
	 */
	private $printDebugCheckConfig;

	/**
	 * @var float how much can the reindexed copy of an index is allowed to deviate from the current
	 * copy without triggering a reindex failure
	 */
	private $reindexAcceptableCountDeviation;

	/**
	 * @var the builder for analysis config
	 */
	private $analysisConfigBuilder;

	/**
	 * @var array(String) list of available plugins
	 */
	private $availablePlugins;

	/**
	 * @var array
	 */
	protected $bannedPlugins;

	/**
	 * @var bool
	 */
	protected $optimizeIndexForExperimentalHighlighter;

	/**
	 * @var array
	 */
	protected $maxShardsPerNode;

	/**
	 * @var int
	 */
	protected $refreshInterval;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update the configuration or contents of one search index." );
		$this->addOption( 'indexType', 'Index to update.  Either content or general.', true, true );
		self::addSharedOptions( $this );
	}

	/**
	 * @param Maintenance $maintenance
	 */
	public static function addSharedOptions( $maintenance ) {
		$maintenance->addOption( 'startOver', 'Blow away the identified index and rebuild it with ' .
			'no data.' );
		$maintenance->addOption( 'indexIdentifier', "Set the identifier of the index to work on.  " .
			"You'll need this if you have an index in production serving queries and you have " .
			"to alter some portion of its configuration that cannot safely be done without " .
			"rebuilding it.  Once you specify a new indexIdentify for this wiki you'll have to " .
			"run this script with the same identifier each time.  Defaults to 'current' which " .
			"infers the currently in use identifier.  You can also use 'now' to set the identifier " .
			"to the current time in seconds which should give you a unique idenfitier.", false, true);
		$maintenance->addOption( 'reindexAndRemoveOk', "If the alias is held by another index then " .
			"reindex all documents from that index (via the alias) to this one, swing the " .
			"alias to this index, and then remove other index.  You'll have to redo all updates ".
			"performed during this operation manually.  Defaults to false." );
		$maintenance->addOption( 'reindexProcesses', 'Number of processess to use in reindex.  ' .
			'Not supported on Windows.  Defaults to 1 on Windows and 5 otherwise.', false, true );
		$maintenance->addOption( 'reindexAcceptableCountDeviation', 'How much can the reindexed ' .
			'copy of an index is allowed to deviate from the current copy without triggering a ' .
			'reindex failure.  Defaults to 5%.', false, true );
		$maintenance->addOption( 'reindexChunkSize', 'Documents per shard to reindex in a batch.   ' .
		    'Note when changing the number of shards that the old shard size is used, not the new ' .
		    'one.  If you see many errors submitting documents in bulk but the automatic retry as ' .
		    'singles works then lower this number.  Defaults to 100.', false, true );
		$maintenance->addOption( 'reindexRetryAttempts', 'Number of times to back off and retry ' .
			'per failure.  Note that failures are not common but if Elasticsearch is in the process ' .
			'of moving a shard this can time out.  This will retry the attempt after some backoff ' .
			'rather than failing the whole reindex process.  Defaults to 5.', false, true );
		$maintenance->addOption( 'baseName', 'What basename to use for all indexes, ' .
			'defaults to wiki id', false, true );
		$maintenance->addOption( 'debugCheckConfig', 'Print the configuration as it is checked ' .
			'to help debug unexepcted configuration missmatches.' );
		$maintenance->addOption( 'justCacheWarmers', 'Just validate that the cache warmers are correct ' .
			'and perform no additional checking.  Use when you need to apply new cache warmers but ' .
			"want to be sure that you won't apply any other changes at an inopportune time." );
		$maintenance->addOption( 'justAllocation', 'Just validate the shard allocation settings.  Use ' .
			"when you need to apply new cache warmers but want to be sure that you won't apply any other " .
			'changes at an inopportune time.' );
	}

	public function execute() {
		global $wgPoolCounterConf,
			$wgLanguageCode,
			$wgCirrusSearchPhraseSuggestUseText,
			$wgCirrusSearchPrefixSearchStartsWithAnyWord,
			$wgCirrusSearchBannedPlugins,
			$wgCirrusSearchOptimizeIndexForExperimentalHighlighter,
			$wgCirrusSearchMaxShardsPerNode,
			$wgCirrusSearchRefreshInterval;

		// Make sure we don't flood the pool counter
		unset( $wgPoolCounterConf['CirrusSearch-Search'] );
		// Set the timeout for maintenance actions
		$this->setConnectionTimeout();

		$this->indexType = $this->getOption( 'indexType' );
		$this->startOver = $this->getOption( 'startOver', false );
		$this->indexBaseName = $this->getOption( 'baseName', wfWikiId() );
		$this->reindexAndRemoveOk = $this->getOption( 'reindexAndRemoveOk', false );
		$this->reindexProcesses = $this->getOption( 'reindexProcesses', wfIsWindows() ? 1 : 5 );
		$this->reindexAcceptableCountDeviation = $this->parsePotentialPercent(
			$this->getOption( 'reindexAcceptableCountDeviation', '5%' ) );
		$this->reindexChunkSize = $this->getOption( 'reindexChunkSize', 100 );
		$this->reindexRetryAttempts = $this->getOption( 'reindexRetryAttempts', 5 );
		$this->printDebugCheckConfig = $this->getOption( 'debugCheckConfig', false );
		$this->langCode = $wgLanguageCode;
		$this->prefixSearchStartsWithAny = $wgCirrusSearchPrefixSearchStartsWithAnyWord;
		$this->phraseSuggestUseText = $wgCirrusSearchPhraseSuggestUseText;
		$this->bannedPlugins = $wgCirrusSearchBannedPlugins;
		$this->optimizeIndexForExperimentalHighlighter = $wgCirrusSearchOptimizeIndexForExperimentalHighlighter;
		$this->maxShardsPerNode = $wgCirrusSearchMaxShardsPerNode;
		$this->refreshInterval = $wgCirrusSearchRefreshInterval;

		try{
			$indexTypes = $this->getAllIndexTypes();
			if ( !in_array( $this->indexType, $indexTypes ) ) {
				$this->error( 'indexType option must be one of ' .
					implode( ', ', $indexTypes ), 1 );
			}

			$this->checkElasticsearchVersion();
			$this->availablePlugins = $this->scanAvailablePlugins( $this->bannedPlugins );

			if ( $this->getOption( 'justCacheWarmers', false ) ) {
				$this->validateCacheWarmers();
				return;
			}

			if ( $this->getOption( 'justAllocation', false ) ) {
				$this->validateShardAllocation();
				return;
			}

			$this->indexIdentifier = $this->pickIndexIdentifierFromOption( $this->getOption( 'indexIdentifier', 'current' ), $this->getIndexTypeName() );
			$this->analysisConfigBuilder = $this->pickAnalyzer( $this->langCode, $this->availablePlugins );
			$this->validateIndex();
			$this->validateAnalyzers();
			$this->validateMapping();
			$this->validateCacheWarmers();
			$this->validateAlias();
			$this->updateVersions();
			$this->indexNamespaces();
		} catch ( \Elastica\Exception\Connection\HttpException $e ) {
			$message = $e->getMessage();
			$this->output( "\nUnexpected Elasticsearch failure.\n" );
			$this->error( "Http error communicating with Elasticsearch:  $message.\n", 1 );
		} catch ( \Elastica\Exception\ExceptionInterface $e ) {
			$type = get_class( $e );
			$message = ElasticsearchIntermediary::extractMessage( $e );
			$trace = $e->getTraceAsString();
			$this->output( "\nUnexpected Elasticsearch failure.\n" );
			$this->error( "Elasticsearch failed in an unexpected way.  This is always a bug in CirrusSearch.\n" .
				"Error type: $type\n" .
				"Message: $message\n" .
				"Trace:\n" . $trace, 1 );
		}
	}

	private function checkElasticsearchVersion() {
		$this->outputIndented( 'Fetching Elasticsearch version...' );
		$result = $this->getClient()->request( '' );
		$result = $result->getData();
		if ( !isset( $result['version']['number'] ) ) {
			$this->output( 'unable to determine, aborting.', 1 );
		}
		$result = $result[ 'version' ][ 'number' ];
		$this->output( "$result..." );
		if ( !preg_match( '/^(1|2)./', $result ) ) {
			$this->output( "Not supported!\n" );
			$this->error( "Only Elasticsearch 1.x is supported.  Your version: $result.", 1 );
		} else {
			$this->output( "ok\n" );
		}
	}

	/**
	 * @param array $bannedPlugins
	 * @return array
	 */
	private function scanAvailablePlugins( array $bannedPlugins = array() ) {
		$this->outputIndented( "Scanning available plugins..." );
		$result = $this->getClient()->request( '_nodes' );
		$result = $result->getData();
		$availablePlugins = array();
		$first = true;
		foreach ( array_values( $result[ 'nodes' ] ) as $node ) {
			$plugins = array();
			foreach ( $node[ 'plugins' ] as $plugin ) {
				$plugins[] = $plugin[ 'name' ];
			}
			if ( $first ) {
				$availablePlugins = $plugins;
				$first = false;
			} else {
				$availablePlugins = array_intersect( $availablePlugins, $plugins );
			}
		}
		if ( count( $availablePlugins ) === 0 ) {
			$this->output( 'none' );
		}
		$this->output( "\n" );
		if ( count( $bannedPlugins ) ) {
			$availablePlugins = array_diff( $availablePlugins, $bannedPlugins );
		}
		foreach ( array_chunk( $availablePlugins, 5 ) as $pluginChunk ) {
			$plugins = implode( ', ', $pluginChunk );
			$this->outputIndented( "\t$plugins\n" );
		}

		return $availablePlugins;
	}

	private function updateVersions() {
		$child = $this->runChild( 'CirrusSearch\Maintenance\UpdateVersionIndex' );
		$child->mOptions['baseName'] = $this->indexBaseName;
		$child->mOptions['update'] = true;
		$child->execute();
		$child->done();
	}

	private function indexNamespaces() {
		// Only index namespaces if we're doing the general index
		if ( $this->indexType === 'general' ) {
			$child = $this->runChild( 'CirrusSearch\Maintenance\IndexNamespaces' );
			$child->execute();
			$child->done();
		}
	}

	private function validateIndex() {
		if ( $this->startOver ) {
			$this->outputIndented( "Blowing away index to start over..." );
			$this->createIndex( true );
			$this->output( "ok\n" );
			return;
		}
		if ( !$this->getIndex()->exists() ) {
			$this->outputIndented( "Creating index..." );
			$this->createIndex( false );
			$this->output( "ok\n" );
			return;
		}
		$this->outputIndented( "Index exists so validating...\n" );
		$this->validateIndexSettings();
	}

	/**
	 * @return \CirrusSearch\Maintenance\Validators\Validator[]
	 */
	private function getIndexSettingsValidators() {
		$validators = array();
		$validators[] = new \CirrusSearch\Maintenance\Validators\NumberOfShardsValidator( $this->getIndex(), $this->getShardCount(), $this );
		$validators[] = new \CirrusSearch\Maintenance\Validators\ReplicaRangeValidator( $this->getIndex(), $this->getReplicaCount(), $this );
		$validators[] = $this->getShardAllocationValidator();
		$validators[] = new \CirrusSearch\Maintenance\Validators\MaxShardsPerNodeValidator( $this->getIndex(), $this->indexType, $this->maxShardsPerNode, $this );
		return $validators;
	}

	private function validateIndexSettings() {
		$validators = $this->getIndexSettingsValidators();
		foreach ( $validators as $validator ) {
			$status = $validator->validate();
			if ( !$status->isOK() ) {
				$this->error( $status->getMessage()->text(), 1 );
			}
		}
	}

	private function validateAnalyzers() {
		$validator = new \CirrusSearch\Maintenance\Validators\AnalyzersValidator( $this->getIndex(), $this->analysisConfigBuilder, $this );
		$validator->printDebugCheckConfig( $this->printDebugCheckConfig );
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}
	}

	private function validateMapping() {
		$validator = new \CirrusSearch\Maintenance\Validators\MappingValidator(
			$this->getIndex(),
			$this->optimizeIndexForExperimentalHighlighter,
			$this->availablePlugins,
			$this->getMappingConfig()->buildConfig(),
			$this->getPageType(),
			$this->getNamespaceType(),
			$this
		);
		$validator->printDebugCheckConfig( $this->printDebugCheckConfig );
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}
	}

	private function validateAlias() {
		$this->outputIndented( "Validating aliases...\n" );
		// Since validate the specific alias first as that can cause reindexing
		// and we want the all index to stay with the old index during reindexing
		$this->validateSpecificAlias();
		$this->validateAllAlias();
	}

	/**
	 * Validate the alias that is just for this index's type.
	 */
	private function validateSpecificAlias() {
		global $wgCirrusSearchMaintenanceTimeout;

		$reindexer = new Reindexer(
			$this->getIndex(),
			Connection::getSingleton(),
			$this->getPageType(),
			$this->getOldPageType(),
			$this->getShardCount(),
			$this->getReplicaCount(),
			$wgCirrusSearchMaintenanceTimeout,
			$this->getMergeSettings(),
			$this->getMappingConfig(),
			$this
		);

		$validator = new \CirrusSearch\Maintenance\Validators\SpecificAliasValidator(
			$this->getClient(),
			$this->getIndexTypeName(),
			$this->getSpecificIndexName(),
			$this->startOver,
			$reindexer,
			array( $this->reindexProcesses, $this->refreshInterval, $this->reindexRetryAttempts, $this->reindexChunkSize, $this->reindexAcceptableCountDeviation ),
			$this->getIndexSettingsValidators(),
			$this->reindexAndRemoveOk,
			$this->tooFewReplicas,
			$this
		);
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}
	}

	public function validateAllAlias() {
		$validator = new \CirrusSearch\Maintenance\Validators\IndexAllAliasValidator( $this->getClient(),
			$this->getIndexName(), $this->getSpecificIndexName(), $this->startOver, $this->getIndexTypeName(), $this );
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}

		if ( $this->tooFewReplicas ) {
			$this->validateIndexSettings();
		}
	}

	protected function validateCacheWarmers() {
		$warmers = new \CirrusSearch\Maintenance\Validators\CacheWarmersValidator( $this->indexType, $this->getPageType(), $this );
		$status = $warmers->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}
	}

	/**
	 * @return \CirrusSearch\Maintenance\Validators\Validator
	 */
	private function getShardAllocationValidator() {
		global $wgCirrusSearchIndexAllocation;
		return new \CirrusSearch\Maintenance\Validators\ShardAllocationValidator( $this->getIndex(), $wgCirrusSearchIndexAllocation, $this );
	}

	protected function validateShardAllocation() {
		$validator = $this->getShardAllocationValidator();
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( $status->getMessage()->text(), 1 );
		}
	}

	private function createIndex( $rebuild ) {
		$maxShardsPerNode = isset( $this->maxShardsPerNode[ $this->indexType ] ) ?
			$this->maxShardsPerNode[ $this->indexType ] : 'unlimited';
		$maxShardsPerNode = $maxShardsPerNode === 'unlimited' ? -1 : $maxShardsPerNode;
		$this->getIndex()->create( array(
			'settings' => array(
				'number_of_shards' => $this->getShardCount(),
				'auto_expand_replicas' => $this->getReplicaCount(),
				'analysis' => $this->analysisConfigBuilder->buildConfig(),
				// Use our weighted all field as the default rather than _all which is disabled.
				'index.query.default_field' => 'all',
				'refresh_interval' => $this->refreshInterval . 's',
				'merge.policy' => $this->getMergeSettings(),
				'routing.allocation.total_shards_per_node' => $maxShardsPerNode,
			)
		), $rebuild );
		$this->tooFewReplicas = $this->reindexAndRemoveOk;
	}

	/**
	 * Pick the index identifier from the provided command line option.
	 * @param string $option command line option
	 *          'now'        => current time
	 *          'current'    => if there is just one index for this type then use its identifier
	 *          other string => that string back
	 * @param string $typeName
	 * @return string index identifier to use
	 */
	private function pickIndexIdentifierFromOption( $option, $typeName ) {
		if ( $option === 'now' ) {
			$identifier = strval( time() );
			$this->outputIndented( "Setting index identifier...${typeName}_${identifier}\n" );
			return $identifier;
		}
		if ( $option === 'current' ) {
			$this->outputIndented( 'Infering index identifier...' );
			$found = array();
			foreach ( $this->getClient()->getStatus()->getIndexNames() as $name ) {
				if ( substr( $name, 0, strlen( $typeName ) ) === $typeName ) {
					$found[] = $name;
				}
			}
			if ( count( $found ) > 1 ) {
				$this->output( "error\n" );
				$this->error( "Looks like the index has more than one identifier. You should delete all\n" .
					"but the one of them currently active. Here is the list: " .  implode( $found, ',' ), 1 );
			}
			if ( $found ) {
				$identifier = substr( $found[0], strlen( $typeName ) + 1 );
				if ( !$identifier ) {
					// This happens if there is an index named what the alias should be named.
					// If the script is run with --startOver it should nuke it.
					$identifier = 'first';
				}
			} else {
				$identifier = 'first';
			}
			$this->output( "${typeName}_${identifier}\n ");
			return $identifier;
		}
		return $option;
	}

	/**
	 * @param string $langCode
	 * @param array $availablePlugins
	 * @return AnalysisConfigBuilder
	 */
	private function pickAnalyzer( $langCode, array $availablePlugins = array() ) {
		$analysisConfigBuilder = new \CirrusSearch\Maintenance\AnalysisConfigBuilder( $langCode, $availablePlugins );
		$this->outputIndented( 'Picking analyzer...' .
			$analysisConfigBuilder->getDefaultTextAnalyzerType() . "\n" );
		return $analysisConfigBuilder;
	}

	/**
	 * @return MappingConfigBuilder
	 */
	protected function getMappingConfig() {
		return new MappingConfigBuilder(
			$this->prefixSearchStartsWithAny,
			$this->phraseSuggestUseText,
			$this->optimizeIndexForExperimentalHighlighter
		);
	}

	/**
	 * @return \Elastica\Index being updated
	 */
	public function getIndex() {
		return Connection::getIndex( $this->indexBaseName, $this->indexType, $this->indexIdentifier );
	}

	/**
	 * @return string name of the index being updated
	 */
	protected function getSpecificIndexName() {
		return Connection::getIndexName( $this->indexBaseName, $this->indexType, $this->indexIdentifier );
	}

	/**
	 * @return string name of the index type being updated
	 */
	protected function getIndexTypeName() {
		return Connection::getIndexName( $this->indexBaseName, $this->indexType );
	}

	/**
	 * @return string
	 */
	protected function getIndexName() {
		return Connection::getIndexName( $this->indexBaseName );
	}

	/**
	 * @return Elastica\Client
	 */
	protected function getClient() {
		return Connection::getClient();
	}

	/**
	 * @return array
	 */
	protected function getAllIndexTypes() {
		return Connection::getAllIndexTypes();
	}

	/**
	 * Get the page type being updated by the search config.
	 *
	 * @return Elastica\Type
	 */
	protected function getPageType() {
		return $this->getIndex()->getType( Connection::PAGE_TYPE_NAME );
	}

	/**
	 * Get the namespace type being updated by the search config.
	 *
	 * @return Elastica\Type
	 */
	protected function getNamespaceType() {
		return $this->getIndex()->getType( Connection::NAMESPACE_TYPE_NAME );
	}

	/**
	 * @return Elastica\Type
	 */
	protected function getOldPageType() {
		return Connection::getPageType( $this->indexBaseName, $this->indexType );
	}

	protected function setConnectionTimeout() {
		global $wgCirrusSearchMaintenanceTimeout;
		Connection::setTimeout( $wgCirrusSearchMaintenanceTimeout );
	}

	/**
	 * Get the merge settings for this index.
	 */
	private function getMergeSettings() {
		global $wgCirrusSearchMergeSettings;

		if ( isset( $wgCirrusSearchMergeSettings[ $this->indexType ] ) ) {
			return $wgCirrusSearchMergeSettings[ $this->indexType ];
		}
		// If there aren't configured merge settings for this index type default to the content type.
		return $wgCirrusSearchMergeSettings[ 'content' ];
	}

	private function getShardCount() {
		global $wgCirrusSearchShardCount;
		if ( !isset( $wgCirrusSearchShardCount[ $this->indexType ] ) ) {
			$this->error( 'Could not find a shard count for ' . $this->indexType . '.  Did you add an index to ' .
				'$wgCirrusSearchNamespaceMappings but forget to add it to $wgCirrusSearchShardCount?', 1 );
		}
		return $wgCirrusSearchShardCount[ $this->indexType ];
	}

	private function getReplicaCount() {
		global $wgCirrusSearchReplicas;

		// If $wgCirrusSearchReplicas is an array of index type to number of replicas then respect that
		if ( is_array( $wgCirrusSearchReplicas ) ) {
			if ( isset( $wgCirrusSearchReplicas[ $this->indexType ] ) ) {
				return $wgCirrusSearchReplicas[ $this->indexType ];
			} else {
				$this->error( 'If wgCirrusSearchReplicas is an array it must contain all index types.', 1 );
			}
		}

		// Otherwise its just a raw scalar so we should respect that too
		return $wgCirrusSearchReplicas;
	}

	private function parsePotentialPercent( $str ) {
		$result = floatval( $str );
		if ( strpos( $str, '%' ) === false ) {
			return $result;
		}
		return $result / 100;
	}
}

$maintClass = "CirrusSearch\Maintenance\UpdateOneSearchIndexConfig";
require_once RUN_MAINTENANCE_IF_MAIN;
