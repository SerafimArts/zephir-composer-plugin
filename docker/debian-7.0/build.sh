#!/usr/bin/env bash

composer install

# =================================
# ========== Run PhpUnit ==========
# =================================

php vendor/bin/phpunit