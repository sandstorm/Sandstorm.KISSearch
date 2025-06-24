<?php
function inArrayFilter(string $columnName, ?array $filterValues, bool $not = false): string
{
    $notString = $not ? 'not' : '';
    if ($filterValues === null || count($filterValues) === 0) {
        return '';
    }
    $valuesForInClause = implode(',', array_map(function($val) { return "'$val'"; }, $filterValues));
    return <<<SQL
        and nd.$columnName $notString in ($valuesForInClause)
    SQL;
}

const SPECIAL_CHARACTERS = '-+~/<>\'":*$#@()!,.?`=%&^';

function prepareSearchTermQueryParameter(string $userInput): string
{
    $sanitized = trim($userInput);
    $sanitized = mb_strtolower($sanitized);
    $sanitized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $sanitized);

    $specialChars = str_split(SPECIAL_CHARACTERS);
    $sanitized = str_replace($specialChars, ' ', $sanitized);

    $searchWords = explode(
        ' ',
        $sanitized
    );

    $searchWords = array_filter($searchWords, function(string $searchWord) {
        return strlen(trim($searchWord)) > 0;
    });

    $searchWordsFuzzy = array_map(function(string $searchWord) {
        return '+' . $searchWord . '*';
    }, $searchWords);

    return implode(' ', $searchWordsFuzzy);
}

// Basic connection settings
/*
$databaseHost = 'maria-db';
$databaseUsername = 'neos';
$databasePassword = 'neos';
$databaseName = 'neos';
$databasePort = 3306;
*/
$databaseHost = 'host.docker.internal';
$databaseUsername = 'root';
$databasePassword = 'password';
$databaseName = 'wwwneosio';
$databasePort = 3307;

$searchQuery = $_GET['q'];
$searchQueryPrepared = prepareSearchTermQueryParameter($searchQuery);
$limit = $_GET['l'] ?? 10;
$workspace = 'live';
$siteNodes = ['neosio'];
$excludedSiteNodes = [];

$dimensionValues = [
    [
        'dimension_name' => 'language',
        'filter_value' => 'en'
    ]
];
$dimensionValuesJson = json_encode($dimensionValues);
// optional node ID filter for sub-tree search
$rootNode = null;
$contentNodeTypes = [];
$documentNodeTypes = [];
$inheritedContentNodeType = null;
$inheritedDocumentNodeType = null;

$siteNodesFilter = inArrayFilter('site_nodename', $siteNodes);
$excludedSiteNodesFilter = inArrayFilter('site_nodename', $excludedSiteNodes, true);
$contentNodeTypeFilter = inArrayFilter('nodetype', $contentNodeTypes);
$documentNodeTypeFilter = inArrayFilter('document_nodetype', $documentNodeTypes);

// Connect to the database
$mysqli = mysqli_connect($databaseHost, $databaseUsername, $databasePassword, $databaseName, $databasePort);

$kissearchQuery = <<<SQL
-- Printing KISSearch search query SQL for endpoint 'us-live'
-- no explicit database type given, detected: MariaDB
-- START OF QUERY
    -- searching query part
    with source__neos_cr__default as
    (select n.*,
        match (search_bucket_critical) against ( ? in boolean mode ) as score_bucket_critical,
        match (search_bucket_major) against ( ? in boolean mode ) as score_bucket_major,
        match (search_bucket_normal) against ( ? in boolean mode ) as score_bucket_normal,
        match (search_bucket_minor) against ( ? in boolean mode ) as score_bucket_minor
    from cr_default_p_graph_node n
    where match (search_bucket_critical, search_bucket_major, search_bucket_normal, search_bucket_minor) against ( ? in boolean mode )
    limit 100),
         all_results as (
            -- union of all search result types aggregated
                (select
        r.result_type                           as result_type,
        r.result_id                             as result_id,
        r.result_title                          as result_title,
        r.result_url                            as result_url,
        max(r.score)                            as score,
        count(r.result_id)                      as match_count,
        json_arrayagg(r.meta_data)              as meta_data,
            json_object(
            'primaryDomain', (select
                           concat(
                               if(d.scheme is not null, concat(d.scheme, ':'), ''),
                               '//', d.hostname,
                               if(d.port is not null, concat(':', d.port), '')
                           )
                       from neos_neos_domain_model_domain d
                       where d.persistence_object_identifier = r.primarydomain
                       and d.active = 1),
            'documentNodeType', r.document_nodetype,
            'siteNodeName', r.site_nodename,
            'dimensionsHash', r.dimensionshash,
            'dimensionValues', r.dimensionvalues,
            'contentstreamid', r.contentstreamid,
            'workspace', r.workspace_name
        )                  as group_meta_data
    from (    select
        -- KISSearch API
        'neos-document' as result_type,
        concat_ws('__', nd.document_id, nd.dimensionshash, nd.contentstreamid) as result_id,
        nd.document_title as result_title,
        nd.document_uri_path as result_url,
        (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor) as score,
        json_object(
            'score', (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor),
            'nodeIdentifier', nd.node_id,
            'nodeType', nd.nodetype,
            'dimensionsHash', nd.dimensionshash,
            'dimensionValues', nd.dimensionvalues,
            'contentstreamid', nd.contentstreamid,
            'workspace', nd.workspace_name
        ) as meta_data,
        -- additional data for later meta data
        s.primarydomain as primarydomain,
        nd.document_nodetype as document_nodetype,
        nd.site_nodename as site_nodename,
        nd.dimensionshash as dimensionshash,
        nd.dimensionvalues as dimensionvalues,
        nd.contentstreamid as contentstreamid,
        nd.workspace_name as workspace_name
    -- for all nodes matching search terms, we have to find the corresponding document node
    -- to link to the content in the search result rendering
    from source__neos_cr__default n
        -- inner join filters hidden and deleted nodes
        inner join sandstorm_kissearch_nodes_and_their_documents_default nd
            on nd.relationanchorpoint = n.relationanchorpoint
        inner join neos_neos_domain_model_site s
            on s.nodename = nd.site_nodename
    where
        -- filter deactivated sites TODO
        s.state = 1
        -- additional query parameters
        and (
            ? is null or nd.workspace_name = ? 
        )
        -- site node name (optional, if null all sites are searched)
        $siteNodesFilter
        -- excluded site node name (optional, if null all sites are searched)
        $excludedSiteNodesFilter
        and (
            -- content dimension values (optional, if null all dimensions are searched)
            ? is null
            or sandstorm_kissearch_all_dimension_values_match(
                    ?,
                    nd.dimensionvalues
            )
        )
        and (
            ? is null or json_contains(nd.parent_documents, json_quote(?))
        )
        $contentNodeTypeFilter
        $documentNodeTypeFilter
        and (
            ? is null or json_contains(nd.inherited_nodetypes, json_quote(?))
        )
        and (
            ? is null or json_contains(nd.inherited_document_nodetypes, json_quote(?))
        )) r
    group by r.result_id
    order by r.score desc
    limit ?)
         )
    select
        -- select all search results
        a.result_id as result_id,
        a.result_type as result_type,
        a.result_title as result_title,
        a.result_url as result_url,
        a.score as score,
        a.match_count as match_count,
        a.group_meta_data as group_meta_data,
        a.meta_data as meta_data
    from all_results a
    order by score desc    -- global limit
    limit ?;
-- END OF QUERY
SQL;
//echo "<pre>";
//var_dump($kissearchQuery);
//echo "</pre>";

$stmt = $mysqli->prepare($kissearchQuery);
$stmt->bind_param(
    "sssssssssssssssii",
    $searchQueryPrepared,
    $searchQueryPrepared,
    $searchQueryPrepared,
    $searchQueryPrepared,
    $searchQueryPrepared,
    $workspace,
    $workspace,
    $dimensionValuesJson,
    $dimensionValuesJson,
    $rootNode,
    $rootNode,
    $inheritedContentNodeType,
    $inheritedContentNodeType,
    $inheritedDocumentNodeType,
    $inheritedDocumentNodeType,
    $limit,
    $limit,
);

// fire query
$stmt->execute();
$result = $stmt->get_result();
$resultCount = $result->num_rows;
$searchResults = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

//echo "<pre>";
//var_dump($resultCount);
//var_dump($searchResults);
//echo "</pre>";
//die();

header('Content-Type: application/json');

echo json_encode([
    'query' => $searchQuery,
    'limit' => $limit,
    'hits' => $resultCount,
    'results' => $searchResults
]);
?>
