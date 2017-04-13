# Zephir Composer Plugin

Your can found example here: [SerafimArts/zephir-example](https://github.com/SerafimArts/zephir-example)

## Usage

1) Open your [`composer.json`](https://getcomposer.org/doc/01-basic-usage.md).
2) Add path to [`config.json`](https://docs.zephir-lang.com/en/latest/config.html) into `extra` section, like:
```json
{
    "require": {
        ...
    },
    "extra": {
        "zephir": [
            "src/config.json"            
        ]
    }
}
```

## Usage example

See [zephir-example](https://github.com/SerafimArts/zephir-example)

```json
{
    "require": {
        "serafim/zephir-composer-plugin": "dev-master@dev",
        "serafim/zephir-example": "~1.0"
    }
}
```