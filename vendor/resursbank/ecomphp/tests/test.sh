#!/bin/bash

phpunit=`which phpunit`
testfile=`ls -1 ecomphp-*.php`

if [ "$phpunit" = "" ] ; then
        if [ -f "./phpunit.phar" ] ; then
                phpunit="./phpunit.phar"
        fi
fi

if [ "$phpunit" != "" ] ; then
        ${phpunit} ${testfile}
else
        echo "No phpunit.phar found"
fi
