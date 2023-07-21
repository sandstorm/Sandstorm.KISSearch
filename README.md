# Sandstorm.KISSearch - pure SQL search for Neos +X

Extensible, low impact, self-contained, SQL-pure stupidly simple search integration for Neos.

Search with the power of SQL fulltext queries. No need for additional infrastructure, works purely with your existing DB.

Supports:
 - MariaDB/MySQL -> first working draft
 - PostgreSQL -> supported very soon

Next Steps:
 - migration tooling
 - PostgreSQL support

still early WIP phase, TODO documentation!

## how to install

TODO: use composer - doc after first release

## how to use

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

### prepare your database for search

Create SQL index:
```shell
./flow kissearch:migrate
```

Remove SQL index:
```shell
./flow kissearch:remove
```

### run search queries

Search on command line:
```shell
# without URL generator
./flow kissearch:search --query "Neos" --limit 100

# with URL generator
./flow kissearch:searchFrontend --query "Neos" --limit 100
```

Flow Service API:
```injectablephp

use Sandstorm\KISSearch\Service\SearchQuery;
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
        $searchResults = $this->searchService->searchFrontend(new SearchQuery($searchQueryUserInput, self::DEFAULT_SEARCH_LIMIT));
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
        $searchResults = $this->searchService->search(new SearchQuery($searchQueryUserInput, self::DEFAULT_SEARCH_LIMIT));
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

The Fusion objects `Sandstorm.KISSearch:Search` and `Sandstorm.KISSearch:SearchFrontend` have cache mode `uncached` by default.

Fusion Eel Helper API:
```neosfusion
// includes URLs of the document nodes
root = ${KISSearch.searchFrontend(request.arguments.q, 100)}
root.@process.json = ${Json.stringify(value)}

// includes only IDs to the document nodes
root = ${KISSearch.search(request.arguments.q, 100)}
root.@process.json = ${Json.stringify(value)}
```

When using EelHelpers, you probably want to set the cache mode of the component that uses them to `uncached`.

## how to extend

TODO

## how to develop

### run tests

run in container:
```
bin/behat -c Packages/Application/Sandstorm.KISSearch/Tests/Behavior/behat.yml.dist -vvv Packages/Application/Sandstorm.KISSearch/Tests/Behavior/Features/
```
