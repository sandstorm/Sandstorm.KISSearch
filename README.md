# TODO

- remove '\n' and '\r' characters in fulltext extraction
- detect required 'utf8mb4' charset for mariaDB and throw readable error in migrate command
  - update README, that utf8mb4 is required when using mariaDB

# Sandstorm.KISSearch - pure SQL search for Neos +X

Extensible, low impact, self-contained, SQL-pure stupidly simple search integration for Neos.
Search with the power of SQL fulltext queries. No need for additional infrastructure, works purely with your existing
DB.

Search configuration is more or less downwards compatible to Neos.SearchPlugin / SimpleSearch / ElasticSearch.

Supports:

- MariaDB version >= 10.6
- MySQL version >= 8.0
- PostgreSQL -> supported very soon

Next Steps:

- migration tooling
- PostgreSQL support

still early WIP phase, TODO more documentation!

## Why KISSearch?

- no additional infrastructure required (like ElasticSearch or MySQLite)
- no explicit index building after changing nodes required, run the SQL migration... that's it :)
- still performant due to fulltext indexing on database level
- easy to extend with additional SearchResultTypes (f.e. tables from your database and/or custom flow entities like
  products, etc.)
  - high level and low level extension API
- comes with Neos Content as default SearchResultType
- designed to be database agnostic
- search multiple sources with a single, performant SQL query

## Brief architecture overview

KISSearch has some abstraction layers:

1. underlying database system (MariaDB, MySQL, Postgres, ...)
2. search result types API easily usable for custom search result types that lives in your database (e.g. products in a
   shop)

- the `neos_content` search result type comes shipped with this package, internally it uses the same API
- each search result type can declare their own additional query parameters (see f.e.
  the [neos_content parameter section](# neos_content additional parameters))

Configuration happens via Settings.yaml

There are three public APIs that you can use for searching:

1. PHP API - the Flow singleton `SearchService` class; f.e. out of your flow controller
2. Fusion API - all objects in the `Sandstorm.KISSearch` namespace; run search queries from within your Fusion
   components
3. Flow CLI Command - run search commands on the command line; most likely for debugging

## known bugs / current TODOs

- migrations must be transactional -> currently, they are not and may leave an inconsistent migration status on errors

## how to install

TODO: use composer - doc after first release

## how to use

### Package configuration

Default config that lives inside the Sandstorm.KISSearch package:

```yaml
Sandstorm:
  KISSearch:
    # database type; mainly required for minimal version checking
    # supported databases:
    #  - MySQL
    #  - MariaDB
    #  - PostgreSQL (coming soon)
    databaseType: 'MariaDB'

    # all registered search result types
    searchResultTypes:
      # default search result type: Neos Content (Nodes from Content Repository)
      neos_content: 'Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentSearchResultType'

    # configuration for the Neos Content search result type
    neosContent:
      # explicit filter to exclude specific node types from search indexing
      excludedNodeTypes:
        - 'Neos.Neos:Shortcut'
      # only document node types that extends this get indexed
      # also only content nodes that lives below documents extending this get indexed
      baseDocumentNodeType: 'Neos.Neos:Document'
      # only content node types that extends this get indexed
      baseContentNodeType: 'Neos.Neos:Content'
```

Write your own search result types and register them via config:

```yaml
Sandstorm:
  KISSearch:
    searchResultTypes:
      # extensibility for your custom search result types 
      my_products: 'Vendor\YourProject\SearchResultTypes\YourTypes\ProductSearchResultType'
```

Important: The custom search result class must be a flow service known by the object manager, and it must implement the
interface `Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeInterface`.

## shipped Neos search result type

Internal name: `neos_content`

Implemented using the public search result type API, KISSearch comes with *Neos content search* shipped <3

### neos_content additional parameters

| Name                            | Description                         | Type                                                             | Required |
|---------------------------------|-------------------------------------|------------------------------------------------------------------|----------|
| neosContentSiteNodeName         | side node name allow list filter    | string, array of strings (also supports NodeName value objects)  | optional |
| neosContentExcludedSiteNodeName | side node name deny list filter     | string, array of strings (also supports NodeName value objects)  | optional |
| neosContentDimensionValues      | content dimension value node filter | array of arrays, dimension name mapped to array of target values | optional |

Example CLI:
```shell
./flow kissearch:search --query "Neos" --additional-params '{"neosContentDimensionValues": {"language": ["en_US"]}, "neosContentSiteNodeName": "neosdemo"}'
```

### NodeType search configuration

Mode: Extract text into single bucket.

```yaml
'Vendor:My.NodeType':
  properties:
    'myProperty':
      search:
        # possible values: 'critical', 'major', 'normal', 'minor'
        bucket: 'major'
```

Mode: Extract HTML tags into specific buckets.

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

This package is compatible to the fulltext extraction configuration used by Neos.SearchPlugin / Neos.SimpleSearch /
Neos.ElasticSearch.

### prepare your database for search

Create SQL index:

```shell
./flow kissearch:migrate
```

Remove SQL index:

```shell
./flow kissearch:remove
```

Check required minimum database version:

```shell
# if not fulfilled, this will print an error message and exit with code 1
./flow kissearch:checkVersion
```

### Additional search query parameters

In general, search result types can bring own additional query parameters. They are basically key-value pairs and
live in two places:

1. pass additional parameter values into the public search API
2. additional parameters are declared in the SQL query to pass in dynamic values

### run search queries

Search on command line:

```shell
# without URL generator
./flow kissearch:search --query "Neos" --limit 100

# with URL generator
./flow kissearch:searchFrontend --query "Neos" --limit 100

# limit by result type - without URL generator
./flow kissearch:searchLimitPerResultType --query "Neos" --limit '{"neos_content": 50, "product": 50}'

# limit by result type - with URL generator
./flow kissearch:searchFrontendLimitPerResultType --query "Neos" --limit '{"neos_content": 50, "product": 50}'
```

Flow Service API:

```injectablephp

use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultFrontend;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Annotations\Inject;

#[Scope('singleton')]
class SearchController extends ActionController {

    private const DEFAULT_SEARCH_LIMIT = 100;

    #[Inject]
    protected SearchService $searchService;
    
    /**
     * Includes URLs to the document nodes.
     * 
     * @param string $searchQueryUserInput the search term user input
     * @return string search results as JSON
     */
    public function searchFrontendAction(string $searchQueryUserInput): string
    {
        /** @var SearchResultFrontend[] $searchResults */
        $searchResults = $this->searchService->searchFrontend(
            new SearchQueryInput(
                $searchQueryUserInput,
                [
                    'neosContentSiteNodeName' => ['foobar'],
                    'neosContentExcludedSiteNodeName' => ['site-i-want-to-exclude'],
                    'neosContentDimensionValues' => [
                    
                    ]
                ]
            ),
            self::DEFAULT_SEARCH_LIMIT
        );
        return json_encode($searchResults);
    }    

    /**
     * Includes only IDs of the document nodes.
     * 
     * @param string $searchQueryUserInput the search term user input
     * @return string search results as JSON
     */
    public function searchAction(string $searchQueryUserInput): string
    {
        /** @var SearchResult[] $searchResults */
        $searchResults = $this->searchService->search(new SearchQueryInput($searchQueryUserInput, self::DEFAULT_SEARCH_LIMIT));
        return json_encode($searchResults);
    }
}
```

Fusion Object API:

```neosfusion
// includes URLs of the document nodes
root = Sandstorm.KISSearch:SearchFrontend {
  query = ${request.arguments.q}
  limit = 100
  @process.json = ${Json.stringify(value)}
}

// includes only IDs to the document nodes
root = Sandstorm.KISSearch:Search {
  query = ${request.arguments.q}
  limit = 100
  @process.json = ${Json.stringify(value)}
}
```

Fusion Eel Helper API:

```neosfusion
// includes URLs of the document nodes
root = ${KISSearch.searchFrontend(request.arguments.q, 100)}
root.@process.json = ${Json.stringify(value)}

// includes only IDs to the document nodes
root = ${KISSearch.search(request.arguments.q, 100)}
root.@process.json = ${Json.stringify(value)}
```

TODO document additionalParameters!

### Fusion caching

TODO document on how to use with Fusion caching

## how to extend

TODO

## how to develop

### run tests

run in container:

```
bin/behat -c Packages/Application/Sandstorm.KISSearch/Tests/Behavior/behat.yml.dist -vvv Packages/Application/Sandstorm.KISSearch/Tests/Behavior/Features/
```
