#!/bin/bash

# Compatibility generator for NetCurl v1.0
# Remove content after namespace.
# sed -e '1,/namespace/d' module_*.php >network.php

src=`grep source\/ composer.json | sed 's/[\"|,$]//g'`
mergeTo="source/build/netcurl.php"

namespace="TorneLIB"

for mergeFile in ${src}
do
    if [ ! ${firstFile} ] ; then
        firstFile=1
        echo "Initializing merge with ${mergeFile}"
        cat ${mergeFile} >${mergeTo}
    else
        if [ ! -d ${mergeFile} ] ; then
            echo "Merging ${mergeFile} into ${mergeTo}"
            sed -e '1,/namespace/d' ${mergeFile} >>${mergeTo}
        fi
    fi
done

crypto=`find |grep tornevall_crypto.php`
if [ "" != "$crypto" ] ; then
    echo "Merging ${crypto} into ${mergeTo}"
    sed -e '1,/namespace/d' ${crypto} >>${mergeTo}
fi

io=`find |grep tornevall_io.php`
if [ "" != "$io" ] ; then
    echo "Merging ${io} into ${mergeTo}"
    sed -e '1,/namespace/d' ${io} >>${mergeTo}
fi

if [ "" != "$1" ] ; then
    # Usage:
    # ./merge.sh "Resursbank\\\\RBEcomPHP"
    # ./merge.sh Tornevall_WP_DNSBL
    echo "Update merged library with new namespace: $namespace => $1"
    sed -i "s/namespace TorneLIB/namespace $1/" ${mergeTo}
    sed -i "s/\&\& \! class_exists( 'TorneLIB\\\\/\&\& \! class_exists( '$1\\\\/g" ${mergeTo}
fi
