<?php
// This file is generated by scripts/gen-autoload.php, do not adjust manually
// @codingStandardsIgnoreFile
global $wgAutoloadClasses;

$wgAutoloadClasses += array(
	'CirrusSearch' => __DIR__ . '/includes/CirrusSearch.php',
	'CirrusSearch\\Api\\ApiBase' => __DIR__ . '/includes/Api/ApiBase.php',
	'CirrusSearch\\Api\\ConfigDump' => __DIR__ . '/includes/Api/ConfigDump.php',
	'CirrusSearch\\Api\\FreezeWritesToCluster' => __DIR__ . '/includes/Api/FreezeWritesToCluster.php',
	'CirrusSearch\\Api\\MappingDump' => __DIR__ . '/includes/Api/MappingDump.php',
	'CirrusSearch\\Api\\SettingsDump' => __DIR__ . '/includes/Api/SettingsDump.php',
	'CirrusSearch\\Api\\Suggest' => __DIR__ . '/includes/Api/Suggest.php',
	'CirrusSearch\\Api\\SuggestIndex' => __DIR__ . '/includes/Api/SuggestIndex.php',
	'CirrusSearch\\BuildDocument\\Builder' => __DIR__ . '/includes/BuildDocument/Builder.php',
	'CirrusSearch\\BuildDocument\\FileDataBuilder' => __DIR__ . '/includes/BuildDocument/FileDataBuilder.php',
	'CirrusSearch\\BuildDocument\\IncomingsLinksScoringMethod' => __DIR__ . '/includes/BuildDocument/SuggestScoring.php',
	'CirrusSearch\\BuildDocument\\PageDataBuilder' => __DIR__ . '/includes/BuildDocument/PageDataBuilder.php',
	'CirrusSearch\\BuildDocument\\PageTextBuilder' => __DIR__ . '/includes/BuildDocument/PageTextBuilder.php',
	'CirrusSearch\\BuildDocument\\ParseBuilder' => __DIR__ . '/includes/BuildDocument/Builder.php',
	'CirrusSearch\\BuildDocument\\QualityScore' => __DIR__ . '/includes/BuildDocument/SuggestScoring.php',
	'CirrusSearch\\BuildDocument\\RedirectsAndIncomingLinks' => __DIR__ . '/includes/BuildDocument/RedirectsAndIncomingLinks.php',
	'CirrusSearch\\BuildDocument\\SuggestBuilder' => __DIR__ . '/includes/BuildDocument/SuggestBuilder.php',
	'CirrusSearch\\BuildDocument\\SuggestScoringMethod' => __DIR__ . '/includes/BuildDocument/SuggestScoring.php',
	'CirrusSearch\\BuildDocument\\SuggestScoringMethodFactory' => __DIR__ . '/includes/BuildDocument/SuggestScoring.php',
	'CirrusSearch\\CheckIndexes' => __DIR__ . '/maintenance/checkIndexes.php',
	'CirrusSearch\\CirrusIsSetup' => __DIR__ . '/maintenance/cirrusNeedsToBeBuilt.php',
	'CirrusSearch\\ClusterSettings' => __DIR__ . '/includes/ClusterSettings.php',
	'CirrusSearch\\Connection' => __DIR__ . '/includes/Connection.php',
	'CirrusSearch\\DataSender' => __DIR__ . '/includes/DataSender.php',
	'CirrusSearch\\Dump' => __DIR__ . '/includes/Dump.php',
	'CirrusSearch\\ElasticsearchIntermediary' => __DIR__ . '/includes/ElasticsearchIntermediary.php',
	'CirrusSearch\\Extra\\Filter\\IdHashMod' => __DIR__ . '/includes/Extra/Filter/IdHashMod.php',
	'CirrusSearch\\Extra\\Filter\\SourceRegex' => __DIR__ . '/includes/Extra/Filter/SourceRegex.php',
	'CirrusSearch\\ForceSearchIndex' => __DIR__ . '/maintenance/forceSearchIndex.php',
	'CirrusSearch\\Hooks' => __DIR__ . '/includes/Hooks.php',
	'CirrusSearch\\InterwikiSearcher' => __DIR__ . '/includes/InterwikiSearcher.php',
	'CirrusSearch\\Job\\DeletePages' => __DIR__ . '/includes/Job/DeletePages.php',
	'CirrusSearch\\Job\\ElasticaWrite' => __DIR__ . '/includes/Job/ElasticaWrite.php',
	'CirrusSearch\\Job\\IncomingLinkCount' => __DIR__ . '/includes/Job/IncomingLinkCount.php',
	'CirrusSearch\\Job\\Job' => __DIR__ . '/includes/Job/Job.php',
	'CirrusSearch\\Job\\LinksUpdate' => __DIR__ . '/includes/Job/LinksUpdate.php',
	'CirrusSearch\\Job\\MassIndex' => __DIR__ . '/includes/Job/MassIndex.php',
	'CirrusSearch\\Job\\OtherIndex' => __DIR__ . '/includes/Job/OtherIndex.php',
	'CirrusSearch\\Maintenance\\AnalysisConfigBuilder' => __DIR__ . '/includes/Maintenance/AnalysisConfigBuilder.php',
	'CirrusSearch\\Maintenance\\ChunkBuilder' => __DIR__ . '/includes/Maintenance/ChunkBuilder.php',
	'CirrusSearch\\Maintenance\\ConfigUtils' => __DIR__ . '/includes/Maintenance/ConfigUtils.php',
	'CirrusSearch\\Maintenance\\CopySearchIndex' => __DIR__ . '/maintenance/copySearchIndex.php',
	'CirrusSearch\\Maintenance\\DumpIndex' => __DIR__ . '/maintenance/dumpIndex.php',
	'CirrusSearch\\Maintenance\\FreezeWritesToCluster' => __DIR__ . '/maintenance/freezeWritesToCluster.php',
	'CirrusSearch\\Maintenance\\IndexDumperException' => __DIR__ . '/maintenance/dumpIndex.php',
	'CirrusSearch\\Maintenance\\IndexNamespaces' => __DIR__ . '/maintenance/indexNamespaces.php',
	'CirrusSearch\\Maintenance\\Maintenance' => __DIR__ . '/includes/Maintenance/Maintenance.php',
	'CirrusSearch\\Maintenance\\MappingConfigBuilder' => __DIR__ . '/includes/Maintenance/MappingConfigBuilder.php',
	'CirrusSearch\\Maintenance\\Reindexer' => __DIR__ . '/includes/Maintenance/Reindexer.php',
	'CirrusSearch\\Maintenance\\RunSearch' => __DIR__ . '/maintenance/runSearch.php',
	'CirrusSearch\\Maintenance\\StreamingForkController' => __DIR__ . '/includes/Maintenance/StreamingForkController.php',
	'CirrusSearch\\Maintenance\\SuggesterAnalysisConfigBuilder' => __DIR__ . '/includes/Maintenance/SuggesterAnalysisConfigBuilder.php',
	'CirrusSearch\\Maintenance\\SuggesterMappingConfigBuilder' => __DIR__ . '/includes/Maintenance/SuggesterMappingConfigBuilder.php',
	'CirrusSearch\\Maintenance\\UpdateOneSearchIndexConfig' => __DIR__ . '/maintenance/updateOneSearchIndexConfig.php',
	'CirrusSearch\\Maintenance\\UpdateSearchIndexConfig' => __DIR__ . '/maintenance/updateSearchIndexConfig.php',
	'CirrusSearch\\Maintenance\\UpdateSuggesterIndex' => __DIR__ . '/maintenance/updateSuggesterIndex.php',
	'CirrusSearch\\Maintenance\\UpdateVersionIndex' => __DIR__ . '/maintenance/updateVersionIndex.php',
	'CirrusSearch\\Maintenance\\Validators\\AnalyzersValidator' => __DIR__ . '/includes/Maintenance/Validators/AnalyzersValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\CacheWarmersValidator' => __DIR__ . '/includes/Maintenance/Validators/CacheWarmersValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\IndexAliasValidator' => __DIR__ . '/includes/Maintenance/Validators/IndexAliasValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\IndexAllAliasValidator' => __DIR__ . '/includes/Maintenance/Validators/IndexAllAliasValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\IndexValidator' => __DIR__ . '/includes/Maintenance/Validators/IndexValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\MappingValidator' => __DIR__ . '/includes/Maintenance/Validators/MappingValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\MaxShardsPerNodeValidator' => __DIR__ . '/includes/Maintenance/Validators/MaxShardsPerNodeValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\NumberOfShardsValidator' => __DIR__ . '/includes/Maintenance/Validators/NumberOfShardsValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\ReplicaRangeValidator' => __DIR__ . '/includes/Maintenance/Validators/ReplicaRangeValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\ShardAllocationValidator' => __DIR__ . '/includes/Maintenance/Validators/ShardAllocationValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\SpecificAliasValidator' => __DIR__ . '/includes/Maintenance/Validators/SpecificAliasValidator.php',
	'CirrusSearch\\Maintenance\\Validators\\Validator' => __DIR__ . '/includes/Maintenance/Validators/Validator.php',
	'CirrusSearch\\NearMatchPicker' => __DIR__ . '/includes/NearMatchPicker.php',
	'CirrusSearch\\OtherIndexes' => __DIR__ . '/includes/OtherIndexes.php',
	'CirrusSearch\\Saneitize' => __DIR__ . '/maintenance/saneitize.php',
	'CirrusSearch\\Sanity\\Checker' => __DIR__ . '/includes/Sanity/Checker.php',
	'CirrusSearch\\Sanity\\NoopRemediator' => __DIR__ . '/includes/Sanity/Remediator.php',
	'CirrusSearch\\Sanity\\PrintingRemediator' => __DIR__ . '/includes/Sanity/Remediator.php',
	'CirrusSearch\\Sanity\\QueueingRemediator' => __DIR__ . '/includes/Sanity/QueueingRemediator.php',
	'CirrusSearch\\Sanity\\Remediator' => __DIR__ . '/includes/Sanity/Remediator.php',
	'CirrusSearch\\SearchConfig' => __DIR__ . '/includes/SearchConfig.php',
	'CirrusSearch\\Search\\BoostTemplatesFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\CustomFieldFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\Escaper' => __DIR__ . '/includes/Search/Escaper.php',
	'CirrusSearch\\Search\\FancyTitleResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Search\\Filters' => __DIR__ . '/includes/Search/Filters.php',
	'CirrusSearch\\Search\\FullTextResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Search\\FunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\FunctionScoreChain' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\FunctionScoreDecorator' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\IdResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Search\\IncomingLinksFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\InterwikiResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Search\\InvalidRescoreProfileException' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\LangWeightFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\NamespacesFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\PreferRecentFunctionScoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\RescoreBuilder' => __DIR__ . '/includes/Search/RescoreBuilders.php',
	'CirrusSearch\\Search\\Result' => __DIR__ . '/includes/Search/Result.php',
	'CirrusSearch\\Search\\ResultSet' => __DIR__ . '/includes/Search/ResultSet.php',
	'CirrusSearch\\Search\\ResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Search\\SearchContext' => __DIR__ . '/includes/Search/SearchContext.php',
	'CirrusSearch\\Search\\SearchTextBaseQueryBuilder' => __DIR__ . '/includes/Search/SearchTextQueryBuilders.php',
	'CirrusSearch\\Search\\SearchTextCommonTermsQueryBuilder' => __DIR__ . '/includes/Search/SearchTextQueryBuilders.php',
	'CirrusSearch\\Search\\SearchTextQueryBuilder' => __DIR__ . '/includes/Search/SearchTextQueryBuilders.php',
	'CirrusSearch\\Search\\SearchTextQueryBuilderFactory' => __DIR__ . '/includes/Search/SearchTextQueryBuilders.php',
	'CirrusSearch\\Search\\SearchTextQueryStringBuilder' => __DIR__ . '/includes/Search/SearchTextQueryBuilders.php',
	'CirrusSearch\\Search\\TitleResultsType' => __DIR__ . '/includes/Search/ResultsType.php',
	'CirrusSearch\\Searcher' => __DIR__ . '/includes/Searcher.php',
	'CirrusSearch\\Updater' => __DIR__ . '/includes/Updater.php',
	'CirrusSearch\\UserTesting' => __DIR__ . '/includes/UserTesting.php',
	'CirrusSearch\\Util' => __DIR__ . '/includes/Util.php',
	'CirrusSearch\\Version' => __DIR__ . '/includes/Version.php',
);
