# Zephir Composer Plugin

This is easy way to compile and install zephir sources though composer dependency manager.

![https://habrastorage.org/files/d48/9bb/b6a/d489bbb6aa524b498f76c962eb392088.gif](https://habrastorage.org/files/d48/9bb/b6a/d489bbb6aa524b498f76c962eb392088.gif)

## Usage

1) Add plugin: `composer require serafim/zephir-composer-plugin`
2) Open your [`composer.json`](https://getcomposer.org/doc/01-basic-usage.md).
3) Add path to [`config.json`](https://docs.zephir-lang.com/en/latest/config.html) into `extra`.`zephir` section:
```json
{
    "require": {
        "serafim/zephir-composer-plugin": "dev-master@dev"    
    },
    "extra": {
        "zephir": [
            "your/src/config.json"            
        ]
    }
}
```
4) Run `composer install` or `composer update`

## Fast start (plugin testing)

See [zephir-example](https://github.com/SerafimArts/zephir-example). 
This is an example of "Hello World" zephir extension.

1) Add "hello world" (`serafim/zephir-example`) into your `composer.json`:
```json
{
    "require": {
        "serafim/zephir-composer-plugin": "dev-master@dev",
        "serafim/zephir-example": "~1.0"
    }
}
```
2) Run `composer install` or `composer update`