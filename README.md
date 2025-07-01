# KISSearch - simple and extensible full text search for Neos

Search with the power of SQL full text queries. No need for additional infrastructure like ElasticSearch or MySQLite.
KISSearch works purely with your existing PostgreSQL, MariaDB or MySQL database.

Search configuration is more or less downwards compatible to Neos.SearchPlugin / SimpleSearch.

Supported Databases:

- MariaDB version >= 10.6
- MySQL version >= 8.0
- PostgreSQL -> supported very soon

Supports Neos 9+

For Neos 8 use the prototype version from branch `neos8-prototype` (this is not actively maintained though).

<!-- TOC -->
* [KISSearch - simple and extensible full text search for Neos](#kissearch---simple-and-extensible-full-text-search-for-neos)
  * [Why KISSearch?](#why-kissearch)
  * [Neos integration](#neos-integration)
    * [Features](#features)
  * [Installation](#installation)
  * [Brief architecture overview](#brief-architecture-overview)
* [Setup](#setup)
* [generic Configuration API](#generic-configuration-api)
  * [SQL Schema migrations](#sql-schema-migrations)
  * [Query Configuration API](#query-configuration-api)
    * [Search Endpoints](#search-endpoints)
* [Execute a search query](#execute-a-search-query)
  * [PHP API (with Flow dependency injection)](#php-api-with-flow-dependency-injection)
  * [PHP API plain](#php-api-plain)
  * [Fusion API](#fusion-api)
    * [EEL Helpers](#eel-helpers)
      * [KISSearch.input()](#kissearchinput)
      * [KISSearch.search()](#kissearchsearch)
    * [Fusion Objects](#fusion-objects)
      * [Sandstorm.KISSearch:SearchInput](#sandstormkissearchsearchinput)
      * [Sandstorm.KISSearch:ExecuteSearchQuery (search)](#sandstormkissearchexecutesearchquery-search)
* [Sandstorm.KISSearch.Neos](#sandstormkissearchneos)
  * [Schema](#schema)
  * [Query](#query)
  * [NodeType search configuration](#nodetype-search-configuration)
    * [Mode: Extract text value into a single bucket.](#mode-extract-text-value-into-a-single-bucket)
    * [Mode: Extract HTML content into specific buckets.](#mode-extract-html-content-into-specific-buckets)
  * [search buckets & score boost](#search-buckets--score-boost)
    * [Score Formular](#score-formular)
    * [Score Aggregator](#score-aggregator)
      * [Example: boost score for individual filters](#example-boost-score-for-individual-filters)
      * [Example: different](#example-different)
<!-- TOC -->

## Why KISSearch?

- no additional infrastructure required (like ElasticSearch or MySQLite)
- no explicit full-text index building after changing nodes required
- easy to extend with additional search result types (f.e. tables from your database and/or custom flow entities like
  products, etc.)
- comes with Neos Content / Neos Documents as default result types
- search multiple sources with a single SQL query
- configure your "search endpoints" plug-and-play style
- good query performance due to full text indexing on database level
    - LIMITS: for now, I tested with 200k nodes which was a bit flaky tbh

## Neos integration

The `Sandstorm.KISSearch.Neos` package namespace implements the KISSearch API to provide full-text search for the Neos 9
ContentRepository. It is intent to be used standalone or in combination with other search sources (most likely your
custom database entities).

Important note:
KISSearch.Neos implements full-text search based on searching nodes in the content repository database.
Those nodes are a tree structure.

The default NeosDocumentQuery implementation comes with a default behavior: 
When finding **content nodes**, they are aggregated to their closest parent document.
A.k.a. your search should **not** result in content nodes but rather **document nodes** containing that content.
The Idea: you always want to find a whole "web page" with a URL to render the search result.

Example node-tree:

```
 - Homepage (document)
   - BlogOverview (document)
     - BlogPage1 (document)     <============== 2. ... then your result should be this document
       - ContentCollection (content)
         - Headline (content) 
         - Text (content)       <============== 1. let's say your search matches a property of this node
     - BlogPage2 (document)
     - ...
   - ...
```

Searching based on the database content makes a pretty huge assumption:
**The child-nodes of document are actually rendered below their document**
In the example above, KISSearch assumes, that the `Text` content node is rendered when requesting `BlogPage1`.

This breaks when you heavily use rendering of cross-referenced nodes. F.e. you never render the Text node, then your 
search result can be misleading or confusing for the end user.  

In this case, you're probably better using a crawler based search engine that indexes your
actual rendered output.

### Features

- shipped query for document search (match results are grouped by their closest parent Document node)
- shipped query for node search (just return plain nodes, don't care if they are Content or Documents)
- backend search bar integration (enable/disable via Settings.yaml)
- Fusion API
- Search configuration via NodeType yaml (compatible to Neos.SimpleSearch)
- auto-update search dependencies on node publish (enable/disable via Settings.yaml)
- flow commands for debugging

What's next?

- default REST API Controller
- Neos integration: score boost based on node age
- KISSearch backend module
    - execute and debug search queries
    - see configuration

## Installation

```
composer require sandstorm/kissearch
```

## Brief architecture overview

- for now, this is a mono-repo, containing multiple namespaces
- This repository contains **three** sub-namespaces:
    - `Sandstorm.KISSearch.Api` =>
      Core Package
    - `Sandstorm.KISSearch.Flow` =>
      Framework integration for Flow (read configuration from settings, use CDI for certain cases)
    - `Sandstorm.KISSearch.Neos` =>
      Ships the migration and query builder for full text searching the ContentRepository
- The KISSearch core is designed to be a pure composer library, so you may use KISSearch in a Laravel or Symphony
  project (as you can with the new ContentRepository of Neos 9).
- Everything is optimized for query time, meaning: there is no need to boot Flow when firing search queries.
- KISSearch abstracts the underlying database system to a certain degree (MariaDB, MySQL, Postgres, ...).
- There is a search result type extension API. Utilize this API for searching custom data that lives in your database
  (e.g. products in a shop).
- The Neos Documents search result type comes shipped with this package, internally it uses the search result type
  extension API. This can be seen as reference implementation.
- You can declare additional facette parameters (see f.e. the NeosDocumentQuery as example).
- Configuration happens via Settings.yaml when using KISSearch in a Flow project.

# Setup

setup database schema initially:

```
./flow kissearch:schemacreate
```

setup database after code updates (f.e. to your NodeType configuration):

```
./flow kissearch:schemareset
```

refresh search dependencies:

```
./flow kissearch:refresh
```

IMPORTANT:
currently, when you have lots of nodes in your DB, the schema create command can fail.
To solve that, reset your ContentRepository projections, then create the KISSearch schema
and finally, update your CR subscriptions again.

# generic Configuration API

## SQL Schema migrations

configure via Settings.yaml

```yaml
Sandstorm:
  KISSearch:
    # ...

    schemas:

      'your-schema-name':
        # class that creates the SQL to create and drop your DB search schema extensions
        class: \Vendor\PackageName\YourSearchSchemaClass
        # class that creates the SQL to refresh your search dependencies
        refresher: \Vendor\PackageName\YourRefresherClass

        # Generic options, that are passed to the create/drop functions.
        options:
          'my-option': 'some-value'
```

Expected PHP interfaces:

| configuration key | expected interface                                                   |
|-------------------|----------------------------------------------------------------------|
| class             | `\Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface`              |
| refresher         | `\Sandstorm\KISSearch\Api\Schema\SearchDependencyRefresherInterface` |

As example, see the `neos-default-cr` search schema inside the `Sandstorm.KISSearch.Neos`.

## Query Configuration API

A KISSearch search query contains three important parts:

* search sources
* result filters
* type aggregators

Search Sources contains the actual full-text queries, result filters add additional filter conditions
and JOINs to other relations. Finally, the type aggregators GROUPs the match results to their final result item.

To configure classes providing all of those SQL parts, there are three configuration namespaces:

```yaml
Sandstorm:
  KISSearch:
    query:

      # configure search source classes
      searchSources:
        # search source identifier for later reference in search endpoints
        'your-source-ref-id':
          # class name to generate the full-text match SQL part
          class: \Vendor\PackageName\YourSourceClass

      # configure result filter classes
      resultFilters:
        # result filter identifier for later reference in search endpoints
        'your-filter-ref-id':
          # class name to generate the filters SQL part
          class: \Vendor\PackageName\YourFilterClass
      typeAggregators:
        'your-aggregator-ref-id':
          # class name to generate the grouping aggregator SQL part
          class: \Vendor\PackageName\YourFilterClass
```

Expected PHP interfaces:

| configuration key       | expected interface                                        |
|-------------------------|-----------------------------------------------------------|
| searchSources.*.class   | `\Sandstorm\KISSearch\Api\Query\SearchSourceInterface`    |
| resultFilters.*.class   | `\Sandstorm\KISSearch\Api\Query\ResultFilterInterface`    |
| typeAggregators.*.class | `\Sandstorm\KISSearch\Api\Schema\TypeAggregatorInterface` |

### Search Endpoints

To configure the creation of search query SQL, KISSearch provides a concept called "Search Endpoint".
Here you "wire" together all sources, filters and aggregators you need in your search query.

Why search endpoints?

- customize your query with sources, filters and aggregators
- combine multiple sources
- define default parameters
- use the same filters twice in a query with different parameters
- define query options for the SQL builder

```yaml
Sandstorm:
  KISSearch:
    query:

      # configure your "search endpoints" here
      endpoints:

        # endpoint ID for loading the endpoint via API
        'some-endpoint':

          # generic options, that are passed to the query builders
          queryOptions:
            # just an example how it is used for the Neos CR query
            'contentRepository': 'default'
            # ...

          # add your filters here (at least one)
          filters:
            # All parameters that are passed in via API are expected to be
            # prefixed with this filter ID. See 'defaultParameters'
            'your-filter-id':
              # reference to the result filter
              filter: your-filter-ref-id
              # an identifier of what items the filter will emit
              resultType: your-result
              # specify the required search sources
              sources:
                - your-source-ref-id
              # parameter default values - they can be overridden via API
              # generic list of parameters.
              # The filter must support those parameters.
              # When you want to pass this parameter from outside,
              # it needs to be prefixed with the filter ID.
              # In this case, the parameter name would be: 'your-filter-id__workspace'
              defaultParameters:
                # just an example
                'workspace': 'live'
              # filter options, they can override filter specific query options
              options:
                'someOption': 'value'
          # add your aggregators here (one per result type)
          typeAggregators:
            # this is the result type name
            'your-result':
              # reference to the aggregator
              aggregator: your-aggregator-ref-id
              # aggregator specific options
              options:
                'otherOption': 42
```

Alternatively, you can create such a configuration via plain PHP:

```injectablephp
$myEndpoint = new \Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration(
    endpointIdentifier: 'some-endpoint',
    queryOptions: [
        'contentRepository' => 'default'
    ],
    filters: [
        'your-filter-id': new \Sandstorm\KISSearch\Api\Query\Configuration\ResultFilterConfiguration(
            filterIdentifier: 'your-filter-id',
            resultFilterReference: 'your-filter-ref-id',
            resultType: \Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName::fromString('your-result'),
            requiredSources: ['your-source-ref-id'],
            defaultParameters: [
                'workspace': \Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName::forLive()
            ]
        )
    ],
    typeAggregators: [
        'your-result': 'your-aggregator-ref-id'
    ]
);
```

# Execute a search query

Executing a search query can be done in various ways:

- use the PHP API
- integrate the Fusion API
- call the shipped REST controller
- print out the search query SQL and do whatever you feel like with it
- use the backend module
- use the backend search bar in the Document Tree
- use the shipped flow command

## PHP API (with Flow dependency injection)

Given, you configured a search endpoint with the ID `your-endpoint-id` in the Settings.yaml.

```injectablephp
#[Scope('singleton')]
class YourSearchService {

    // constructor injection
    public function __construct(
        private readonly FlowSearchEndpoints $searchEndpoints, 
        private readonly DatabaseTypeDetector $databaseTypeDetector,
        private readonly FlowCDIObjectInstanceProvider $instanceProvider,
        private readonly DoctrineDatabaseAdapterService $databaseAdapter
    ) {
    }
    
    /**
     * Your search API...
     * 
     * @param string $query
     * @return \Sandstorm\KISSearch\Api\Query\Model\SearchResults
     */
    public function executeMyQuery(string $query): \Sandstorm\KISSearch\Api\Query\Model\SearchResults
    {
        $params = [
           // ...
        ];
        
        $resultLimits = [
            // ...
        ];
        
        // global limit
        $limit = 100;
        
        // ### 0. detect database type
        // may also be hard-coded in your project
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        
        // ### 1. put user input into a SearchInput instance
        $input = new SearchInput(
            // the search query input
            $query,
            // the additional parameters, f.e. Neos workspace, etc.
            // may override default parameters configured in the endpoint
            $params,
            // limit per result type, f.e. ['neos-document' => 20, 'product' => 40]
            $resultLimits,
            // (optional) global limit of all merged results
            // If not given, the sum of all limits per result type is used
            $limit
        );
        
        // ### 2. load your endpoint configuration
        // In this case, we use the shipped Flow service.
        $searchEndpointConfiguration = $this->searchEndpoints
            ->getEndpointConfiguration('your-endpoint-id');
        
        // ### 3. create the search query
        $searchQuery = SearchQuery::create(
            $databaseType,
            $this->instanceProvider,
            $searchEndpointConfiguration,
            // override default query options configured in the endpoint
            $queryOptionsArray
        );
        
        // ### 4. execute the search query
        $results = QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $this->databaseAdapter
        );
        
        return $results;
    }
```

## PHP API plain

Given, you use doctrine and have access to the `EntityManagerInterface` instance.
If you don't use doctrine, implement your own `SearchQueryDatabaseAdapterInterface`.

```injectablephp
final readonly class YourSearchService {

    public static function executeSearch(
        string $query,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): \Sandstorm\KISSearch\Api\Query\Model\SearchResults
    {
        $params = [
           // ...
        ];
        
        $resultLimits = [
            // ...
        ];
        
        // global limit
        $limit = 100;

        // ### 0. create adapter instance
        $databaseType = \Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType::MARIADB;
        $databaseAdapter = new \Sandstorm\KISSearch\Api\DBAbstraction\DoctrineDatabaseAdapter($entityManager);
        
        // ### 1. put user input into a SearchInput instance
        $input = new SearchInput(
            // the search query input
            $query,
            // the additional parameters, f.e. Neos workspace, etc.
            // may override default parameters configured in the endpoint
            $params,
            // limit per result type, f.e. ['neos-document' => 20, 'product' => 40]
            $resultLimits,
            // (optional) global limit of all merged results
            // If not given, the sum of all limits per result type is used
            $limit
        );

        // ### 2. create your endpoint configuration (this could also be done globally elsewhere)
        $searchEndpointConfiguration = new \Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration(
            endpointIdentifier: 'some-neos-endpoint',
            queryOptions: [
                'contentRepository' => 'default'
            ],
            filters: [
                'neos': new \Sandstorm\KISSearch\Api\Query\Configuration\ResultFilterConfiguration(
                    filterIdentifier: 'neos',
                    resultFilterReference: 'neos-document-filter',
                    resultType: \Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName::fromString('neos-document'),
                    requiredSources: ['neos-content'],
                    defaultParameters: [
                        'workspace': \Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName::forLive()
                    ]
                )
            ],
            typeAggregators: [
                'neos-document': 'neos-document-aggregator'
            ]
        )
        
        // ### 3. create your instance provider
        // (if you use plain PHP, you need to instantiate the query builder classes by yourself)
        
        // We re-use the $neosDocumentQuery instance, since it implements both the filter and aggregator.
        // This is optional, though. The classes are all stateless.
        $neosDocumentQuery = new \Sandstorm\KISSearch\Neos\Query\NeosDocumentQuery();
        $instanceProvider = new \Sandstorm\KISSearch\Api\FrameworkAbstraction\DefaultQueryObjectInstanceProvider(
            searchSourceInstances: [
                'neos-content': new \Sandstorm\KISSearch\Neos\Query\NeosContentSource()
            ],
            resultFilterInstances: [
                'neos-document-filter': $neosDocumentQuery
            ],
            typeAggregatorInstances: [
                'neos-document-aggregator': $neosDocumentQuery
            ]
        );

        // ### 4. create the search query
        $searchQuery = SearchQuery::create(
            $databaseType,
            $instanceProvider,
            $searchEndpointConfiguration,
            // override default query options configured in the endpoint
            $queryOptionsArray
        );
        
        // ### 5. execute the search query
        $results = QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $databaseAdapter
        );
        
        return $results;
    }

}
```

## Fusion API

There are two Fusion objects and EEL Helpers that you can use:

### EEL Helpers

#### KISSearch.input()

Creates an instance of `SearchInput` which is required to fire a search query.

```neosfusion
input = ${KISSearch.input('your search query', ['neos__workspace' => 'live'], ['neos-document' => 20], 20)}
```

- Helper name: `KISSearch`
- Function name: `input`
- parameters:
    - searchQuery (string) -> the search query input
    - parameters (array<string, mixed>) -> query parameter values
    - resultTypeLimits (array<string, int>) -> limits per result type
    - limit (int, optional) -> global limit

#### KISSearch.search()

Fires a search query.

```neosfusion
searchResults = ${KISSearch.search('your-endpoint', props.input, ['contentRepository' => 'default'], null)}
```

- Helper name: `KISSearch`
- Function name: `search`
- parameters:
    - endpointId (string) -> which search endpoint to use
    - input (SearchInput) -> the user input
    - queryOptions (array<string, mixed>) -> override query options from endpoint here
    - databaseType (string of enum DatabaseType, optional) -> set explicit database type, auto-detected if null

### Fusion Objects

The Fusion objects basically wrap the EEL Helper calls.

#### Sandstorm.KISSearch:SearchInput

Wrapper for EEL Helper `KISSearch.input()`

Usage:

```neosfusion
input = Sandstorm.KISSearch:SearchInput {
    # the search query user input
    searchQuery = ${request.arguments.q}

    # query parameter values
    parameters {
        # the prefix 'neos__' is the filter identifier configured in the search endpoint
        neos__workspace = 'live'
        neos__dimension_values {
            language = 'en_US'
        }
    }

    # limit per result type 
    resultTypeLimits {
        # the result type name 'neos-document' is configured in the search endpoint
        'neos-document' = 20
    }

    # optional global limit parameter
    limit = null
}
```

Evaluates into an instance of `SearchInput`.

#### Sandstorm.KISSearch:ExecuteSearchQuery (search)

Wrapper for EEL Helper `KISSearch.search()`

```neosfusion

prototype(Vendor:SearchResultList) < prototype(Neos.Fusion:Component) {
    searchResults = Sandstorm.KISSearch:ExecuteSearchQuery {
        # reference the default endpoint
        endpoint = 'neos-default-cr'

        # see doc for search input
        input = Sandstorm.KISSearch:SearchInput {
            # ...
        }

        # override default query options here
        queryOptions {
            # ...
        }

        # set explicit database type or auto-detect (default)
        databaseType = null
    }

    # IMPORTANT: please cache your server-side search result rendering!!!
    # for an example, see the ExampleDefaultQuery prototype
    @cache {
        mode = 'dynamic'
        # see the example query
        # ...
    }

    renderer = afx`
        <ul>
            <Neos.Fusion:Loop items={props.searchResults.results}>
                <li @path='itemRenderer'>
                    {item.title}
                </li>
            </Neos.Fusion:Loop>
        </ul>
    `
}

```

A full Fusion API example can be seen
here: [ExampleDefaultQuery.fusion](Resources/Private/Fusion/_Examples/ExampleDefaultQuery.fusion)

# Sandstorm.KISSearch.Neos

The package `Sandstorm.KISSearch.Neos` (composer `sandstorm/kissearch-neos`) is the implementation of the KISSearch Core
API
that provides full-text search for the Neos **ContentRepository** (Neos 9 ESCR).

## Configuration

The configuration that is specific for the Neos integration lives under the Settings.yaml
namespace `Sandstorm.KISSearch.Neos`

```yaml
Sandstorm:
  KISSearch:
    Neos:

      # Integration of KISSearch in the Neos Backend search bar.
      backendSearch:
        # Enable/Disable the feature. If disabled, the original search endpoint is used
        enabled: true
        # Which KISSearch endpoint to use for the backend search.
        # Expects a filter with ID "neos"
        endpoint: neos-backend

      # Auto refresh dependencies on node publish events
      refresher:
        autoRefreshEnabled: true
```

Enable or disable the backend search bar integration and the auto-refresher on node publish.
You also can customize the search endpoint here, or you change the configuration of the default endpoint `neos-backend`
directly.


## Schema

The package brings a default schema: `neos-default-cr`.

IMPORTANT: each ContentRepository can only have **one single** KISSearch schema.

Apply/remove/re-create the schema via flow command.

## Query

Supported filter parameters for the Neos result filter:

| Parameter name               | Description                                                                                         | Supported type                                               | Example                                                                       |
|------------------------------|-----------------------------------------------------------------------------------------------------|--------------------------------------------------------------|-------------------------------------------------------------------------------|
| workspace                    | the Neos workspace name                                                                             | string, `WorkspaceName`                                      | `"my-workspace"`, `WorkspaceName::forLive()`                                  |
| dimension_values             | the Neos dimension values                                                                           | array<string, string>                                        | `['language' => 'en']`                                                        |
| root_node                    | the root node for a sub-tree search                                                                 | string, `NodeAggregateId`                                    | some UUID                                                                     |
| site_node                    | node name(s) of the site node                                                                       | string, `NodeName`, array<string>, array<`NodeName`>         | `neosdemo`                                                                    |
| excluded_site_node           | exclude this site(s)                                                                                | string, `NodeName`, array<string>, array<`NodeName`>         | `['site-a', 'site-b']`                                                        |
| content_node_types           | filter content NodeTypes (f.e. only search in your Headline nodes)                                  | string, `NodeTypeName`, array<string>, array<`NodeTypeName`> | `"Vendor.Package:Headline"`                                                   |
| document_node_types          | filter for document NodeTypes                                                                       | string, `NodeTypeName`, array<string>, array<`NodeTypeName`> | `["Vendor.Package:Document.BlogPage", "Vendor.Package:Document.ArticlePage"]` |
| inherited_content_node_type  | filter content NodeTypes with inheritance logic (a node matches if it inherits the given NodeType)  | string, `NodeTypeName`                                       | `"Vendor.Package:AbstractSearchableContent"`                                  |
| inherited_document_node_type | filter document NodeTypes with inheritance logic (a node matches if it inherits the given NodeType) | string, `NodeTypeName`                                       | `"Vendor.Package:Document.AbstractSearchablePage"`                            |

## NodeType search configuration

Nodes are found by KISSearch by indexing node properties based on the yaml configuration in the NodeType yaml.
For properties to be indexed, the property value needs to be extracted. How this is performed is configured in two main
ways:

1. indexing a string value node property (extract into single bucket)
2. indexing an HTML-based node property (extract HTML)

### Mode: Extract text value into a single bucket.

The whole property value is put into a single bucket.
Intent to be used for **text based** node properties that are not wrapped by HTML.
Most likely, this is true for inspector properties that are text or textarea fields.
For rich text edited properties, use the HTML extraction mode instead.

```yaml
'Vendor:My.NodeType':
  properties:
    'myProperty':
      search:
        # possible values: 'critical', 'major', 'normal', 'minor'
        bucket: 'major'
```

### Mode: Extract HTML content into specific buckets.

The property contains HTML, most likely produced by the rich text editor.

```yaml
'Vendor:My.Headline':
  superTypes:
    'Neos.NodeTypes.BaseMixins:TextMixin': true
  properties:
    'text':
      search:
        # possible values: 'all', 'critical', 'major', 'normal', 'minor'
        # or an array containing multiple values of: 'critical', 'major', 'normal', 'minor'
        extractHtmlInto: [ 'critical', 'major' ]
```

This package is compatible to the fulltext extraction configuration used by Neos.SearchPlugin / Neos.SimpleSearch /
Neos.ElasticSearch.

Here you can specify a list of buckets. Text content inside different headline tags are sorted into more important
buckets.

| Bucket   | Extracted to index | Filtered out                 |
|----------|--------------------|------------------------------|
| critical | h1, h2             | h3, h4, h5, h6, text content |
| major    | h3, h4, h5, h6     | h1, h2, text content         |
| normal   | text content       | h1, h2, h3, h4, h5, h6       |
| minor    | text content       | h1, h2, h3, h4, h5, h6       |

Note, that minor and normal have the same fulltext extraction behavior. They just result in different scores.

Use the shortcut `all` for a full-spectrum extraction into all buckets.

```yaml
'Vendor:My.ExampleText':
  superTypes:
    'Neos.NodeTypes.BaseMixins:TextMixin': true
  properties:
    'text':
      search:
        # possible values: 'all', 'critical', 'major', 'normal', 'minor'
        # or an array containing multiple values of: 'critical', 'major', 'normal', 'minor'
        #    'all' is not supported as array value
        #    'all' is equivalent to ['critical', 'major', 'normal', 'minor']
        extractHtmlInto: 'all'
```

## search buckets & score boost

So-called "search buckets" are different stores for full-text searchable content that
have different weights in score. A query always searches **all** buckets and calculates an
overall score for the result item, based on wich bucket(s) matched.

In detail:

1. The search sources emit rows that contain a column for each bucket score.
2. The result filter then calculates the overall score, based on a configurable formular.
3. The type aggregator finally decides, how aggregate the score for multiple items that gets grouped to a single
   result (f.e. take the **max** value).

There are four buckets in the Neos KISSearch implementation:

- critical
- major
- normal
- minor

Those buckets can be accessed in the score formular.

### Score Formular

If you customize the formular, it is advised to represent the bucket names in the actual math (critical should boost
most, etc...).

The default score formular **SQL expression** is:

```
(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)
```

Note:
The table alias `n` accesses items emitted by the search source. It stands for "node".
The column names for the individual bucket scores are `score_bucket_critical`, `score_bucket_major`,
`score_bucket_normal` and `score_bucket_minor`.

The default formular is both compatible to MariaDB/MySQL and PostgreSQL.

If you want to customize the **Score Formular**, you can set this via query or filter option in the search endpoint configuration.
You can call database-specific functions here as well, as long as your expression returns a **scalar number** value.

### Score Aggregator

Finally, when the type aggregator combines multiple matches from filters to a single result item, the scores of those
hits are also aggregated. More specific: the Neos filter finds general nodes (Content and Document nodes) and the aggregator
groups by closest parent document. Therefore, the aggregator needs to know how to combine the scores of multiple nodes to
a single document node.
 
The default aggregator **SQL expression** is:

```
max(r.score)
```

Note:
The table alias `r` accesses items emitted by the result filters. It stands for "result".
The column name is `score` and is accessed in a `GROUP BY` select. If you want to customize this behavior via query
option,
make sure your SQL expression is a **SQL aggregate function** that returns a single **scalar number**.

Examples:

- sum up all result hits: `sum(r.score)` -> The more nodes match below the document, the more important the document
  becomes.
- take the max score / default: `max(r.score)` -> The most important node below the document sets the overall score.
- f.e. average ...

Query option names are:

| Database type   | Meaning          | Option Name               | Where to define?                                                              |
|-----------------|------------------|---------------------------|-------------------------------------------------------------------------------|
| MariaDB / MySQL | Score Formular   | `mariadb_scoreFormular`   | query options (for all filers), filter options for a single filter            |
| PostgreSQL      | Score Formular   | `pgsql_scoreFormular`     | query options (for all filers), filter options for a single filter            |
| MariaDB / MySQL | Score Aggregator | `mariadb_scoreAggregator` | query options (for all aggregators), aggregator options for single aggregator |
| PostgreSQL      | Score Aggregator | `pgsql_scoreAggregator`   | query options (for all aggregators), aggregator options for single aggregator |

(there is multi-DB-support, in case you want to support both DB types)

```yaml

Sandstorm:
  KISSearch:
    # ...
    query:
      endpoints:
        # ...
        'your-endpoint':
          queryOptions:
            # Here, we want a cubic/square score sum, to boost critical even higher.
            # Also, in this example we do not consider the minor bucket at all.
            'mariadb_scoreFormular': 'power(n.score_bucket_critical, 3) + power(n.score_bucket_major, 2) + n.score_bucket_normal'
            # The more nodes match, the more important the document gets.
            # Also, here we multiply by a constant value, to even out the score with other search results ...
            'mariadb_scoreAggregator': 'sum(n.score) * 0.5'
            # ...
```

or if you want to boost the individual score of filters:

#### Example: boost score for individual filters

```yaml
Sandstorm:
  KISSearch:
    # ...
    query:
      endpoints:
        # ...
        'your-endpoint':
          # example: boost the score for a specific content dimension
          filters:
            # this filter uses the default score
            'neos-en-uk':
              filter: 'neos-document-filter'
              sources:
                - neos-content-source
              defaultParameters:
                dimension_values:
                  language: en_UK
            'neos-en-us':
              filter: 'neos-document-filter'
              sources:
                - neos-content-source
              defaultParameters:
                dimension_values:
                  language: en_US
              options:
                # We use the default formular, multiplied by 2 -> so we boost US content over UK content
                'mariadb_scoreFormular': '2 * ((20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor))'
          # ...
```

#### Example: different

TODO more examples

# Extending KISSearch with own search results

TODO indepth guide coming soon