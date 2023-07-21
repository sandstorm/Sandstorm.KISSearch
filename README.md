# Sandstorm.KISSearch - pure SQL search for Neos +X

Extensible, low impact, self-contained, SQL-pure stupidly simple search integration for Neos.

still early WIP phase, TODO documentation!

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
