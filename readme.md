# Zephir Composer Plugin

Your can found example here: [SerafimArts/zephir-example](https://github.com/SerafimArts/zephir-example)

![https://habrastorage.org/files/d48/9bb/b6a/d489bbb6aa524b498f76c962eb392088.gif](https://habrastorage.org/files/d48/9bb/b6a/d489bbb6aa524b498f76c962eb392088.gif)

## Usage

1) Open your [`composer.json`](https://getcomposer.org/doc/01-basic-usage.md).
2) Add path to [`config.json`](https://docs.zephir-lang.com/en/latest/config.html) into `extra` section, like:
```json
{
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