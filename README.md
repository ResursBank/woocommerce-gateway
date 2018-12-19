# Resurs Bank for WooCommerce

In the initial days of Resurs ecommerce a plugin was developed to support Resurs Bank in the gateway-verse of WooCommerce. It was, just like this one, released as third party edition for Resurs Bank payment flows.

When time passed, the source code got a bit unhandy to develop with, but since the code was already sent to production environments it was also dependent on functionality. Breaking something in this state is basically not good for business.

This version of the plugin is a rebuild-fork of the prior one, and a try to free some bottlenecks.

## Automatically updating payment methods

This plugin supports the ability to update payment methods automatically via scheduled jobs (like cron). The "only" thing you need is an application that can call for your site by a http call. Here are some examples:

##### curl:

``curl "https://mysite.test.com/wp-admin/admin-ajax.php?action=resurs_bank_backend&run=get_payment_methods&cron"``

##### wget:

``wget -q "http://mysite.test.com/wp-admin/admin-ajax.php?action=resurs_bank_backend&run=get_payment_methods&cron"``

##### GET:

``GET -d "http://mysite.test.com/wp-admin/admin-ajax.php?action=resurs_bank_backend&run=get_payment_methods&cron"``


## Naming and coexistence

The base of this plugin is created in an external fork repo and then, when honored by Resurs Bank, merged into a separated branch called 3.0.

The forking style of this plugin is written so the new release can coexist with the old plugin, which also helps us prevent different types of conflicts.

### Plugins that utilizing each other

If you decide to run this plugin side by side with the prior Resurs-branded release, the both plugins might take advantage of each others code. At least, when it comes to the payment engine EComPHP, we use the same code bases. If this plugin finds out that the prior version is available, it will instead use the already shipped EComPHP. Disabling that plugin, makes it use the built in version instead.

## Available filters and hooks

### resursbank_configrow_scriptloader

Add extending script to specific configuration field row from wp-admin without interfering the core source. Used internally to control behaviour of aftershop flow. First argument is the scriptloader itself, second is for checking which field configuration key that is running. Use with caution as this is all about javascripts. Javascripts might destroy working flows.

Example: A checkbox needs extended actions in the configuration:

````
  add_filter('resursbank_configrow_scriptloader', 'resursbank_configrow_internal_scriptloader', 10, 2);
  
  function resursbank_configrow_internal_scriptloader($scriptLoader, $configKey='') {
  
    if ($configKey === 'test_function_field') {
      $scriptLoader = 'onclick="testFunctionFieldJs()"';
    }
    
    return $scriptLoader;
  }
````


## TODOings

### Transferring this module to the Resurs Bank branch

What always must be done of merging this fork into the Resurs Bank branch

* Translation domain are different, make sure it uses the right name
* Make sure the naming slug matches the other release

### What's been done so far

* All sections for the plugin are split up in smaller pieces to prevent conflicts between the sections when developing features
* Configuration section is no longer following WC config rules as many things are configured dynamically and the WC config policy is too strict
* Cosmetic updates: Logotype for tab configuration follows WC3x-hooks and is also resized to match the tab size

### What should be done

* Administration interface must be pushed out as a primary goal as we are dependent on dynamic configuration
* Warn when prior version of the plugin is running the same as this module
* Filters and hooks should be covered in as many sections as possible
* Multiple countries (Select flow per country)
* Are you running multiple credentials, make sure salt keys for callbacks are separated for each section
* Payment methods should be dynamically loaded and configured
* Handle special metadata differently (using special table for metadata will make them unwritable for users)



#### Migrate into an official branch

If you thinking of developing within the external branch [located here](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/browse) and merge it into the [current official repo](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/), instead of branching/forking the original repo - you might want to take a look at the script below. As development passes you probably want to change some of the parameters in it. It works like this:

* Clone the Resurs Bank repo into resurs-bank-payment-gateway-for-woocommerce
* Clone the other repo into tornevall-networks-resurs-bank-payment-gateway-for-woocommerce
* Make sure that the oldbranch variable points at the correct branch (this is the repo you want to use at Resurs Bank, so make sure it also at least exists before starting). The newbranch value is the source of what you want to put into the official repo.  
* Run the script - if everything goes well, you have the new updated base in your destination repo. Not merge the source, check for differences and create a pull request!

##### migrate.sh

    #!/bin/bash
    
    # Resurs Bank AB plugin for WooCommerce merge-script. Converts the new repo content to a compatible non-disabled
    # module for the official repo.
    
    old=resurs-bank-payment-gateway-for-woocommerce
    new=tornevall-networks-resurs-bank-payment-gateway-for-woocommerce
    
    oldbranch=develop/3.0
    newbranch=develop/1.0
    verbose="-v"
    
    if [ ! -d $old ] && [ ! -d $new ] ; then
        echo "The directories ${old} and ${new} missing in your file structure."
        exit
    fi
    
    whereami=$(pwd | grep $new)
    if [ "" != "$whereami" ] ; then
        echo "It seems that this script is running within a codebase. Not good. Please exit this directory or try again."
        exit
    fi
    
    echo "Preparing branches..."
    
    echo "Refresh ${old}"
    cd ${old}
    
    echo "Branch control (master)"
    curbranch=$(git branch | grep "^*")
    
    echo "Current branch is ${curbranch}"
    if [ "$curbranch" != "* master" ] ; then
        echo "And that was not right. Trying to restore state of branches."
        git reset --hard && git clean -f -d && git checkout master
    else
        echo "And that seem to be correct ..."
    fi
    
    curbranch=$(git branch | grep "^*")
    
    if [ "$curbranch" != "* master" ] ; then
        echo "Something failed during checkout. Aborting!"
        exit
    fi
    
    echo "Current branch is now ${curbranch} ... Syncing!"
    
    git fetch --all -p
    git pull
    echo "Going for ${oldbranch} in ${old}"
    git checkout ${oldbranch}
    git fetch --all -p
    git pull
    
    curbranch=$(git branch | grep "^*")
    echo "Current branch is now ${curbranch}"
    
    if [ "$curbranch" != "* $oldbranch" ] ; then
        echo "I am not in the correct branch. Aborting!"
        exit
    fi
    
    echo "Cleaning up ..."
    find . |grep -v .git|awk '{system("rm -rf \"" $1 "\"")}'
    
    echo "Refresh ${new}"
    
    cd ../${new}
    git checkout master
    git fetch --all -p
    git pull
    echo "Going back to ${newbranch} for ${new}"
    git checkout ${newbranch}
    git fetch --all -p
    git pull
    
    echo "Ok. Now going for the correct source code..."
    
    find . -maxdepth 1 | \
        grep -v .git | \
        grep -v "^.$" | \
        awk '{system("cp -rf \"" $1 "\" ../resurs-bank-payment-gateway-for-woocommerce/")}'
    
    echo "Going back to old branch..."
    
    cd ../${old}
    echo "Old branch goes back to master..."
    
    mv ${verbose} init.php resursbankgateway.php
    sed -i 's/Tornevall Networks Resurs Bank payment gateway for WooCommerce/Plugin Name: Resurs Bank Payment Gateway for WooCommerce/' \
        resursbankgateway.php readme.txt
    sed -i 's/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/resurs-bank-payment-gateway-for-woocommerce/' \
        *.php includes/Resursbank/*.php includes/Resursbank/Helpers/*.php
    
    languages="da_DK en_GB nb_NO sv_SE"
    for lang in $languages
    do
        oldfile="languages/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce-${lang}."
        newfile="languages/resurs-bank-payment-gateway-for-woocommerce-${lang}."
    
        if [ -f ${oldfile}mo ] ; then
            mv ${verbose} ${oldfile}mo ${newfile}mo
            mv ${verbose} ${oldfile}po ${newfile}po
        fi
    done
    
    git checkout master
    
    echo "All done!"
