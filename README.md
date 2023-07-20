-- Interface für SearchResultType ->
--    - CTE Anteil
--    - Scoring query Anteil
--    - Index Builder
--    - URL Builder
--    - type name

-- Interface für SearchQueryBuilder ->
--    - Zusammenbauen der Unions
--    - Zusammenbauen der CTE

## run tests

run in container:
```
bin/behat -c Packages/Application/Sandstorm.KISSearch/Tests/Behavior/behat.yml.dist -vvv Packages/Application/Sandstorm.KISSearch/Tests/Behavior/Features/
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
