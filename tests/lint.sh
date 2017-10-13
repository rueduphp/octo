#!/bin/bash

status=0

cd $(dirname $0)/..
files=$(find lib -name "*.php")
for file in $files; do
    php -l $file
done

exit $status
