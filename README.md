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

See the migration notes below.


### What's been done so far

* All sections for the plugin are split up in smaller pieces to prevent conflicts between the sections when developing features
* Configuration section is no longer following WC config rules as many things are configured dynamically and the WC config policy is too strict
* Cosmetic updates: Logotype for tab configuration follows WC3x-hooks and is also resized to match the tab size
* Administration interface has taken form and is close to finalization.
* Added warnings for prior versions exists in the same plugin structure.
* Filters and hooks is implemented "on fly".
* Multiple countries (Select flow per country).


### What should be done

* The single merchant credential configuration from 2.x should be inherited automatically to the new version.
* Callbacks.
* Aftershop flow.
* Order admin view.
* Are you running multiple credentials, make sure salt keys for callbacks are separated for each section.
* Payment methods should be dynamically loaded and configured.
* Handle special metadata differently (using special table for metadata will make them unwritable for users).



#### Migrate into an official branch

If you thinking of developing within the external branch [located here](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/browse) and merge it into the [current official repo](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/master/), instead of branching/forking the original the better practice is to clone the fork somewhere and then add the fork as a remote to the original repo. Like this:

    git remote add fork https://bitbucket.tornevall.net/scm/www/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce.git

Each time you make changes in the fork, make sure you have a branch in the original repo where you can merge your settings and then create a pull request. In this case, you don't need to do much merging job. However, you need to rename a few things from the fork, so that it matches with the former plugin structure. If you change the structure there might be risks that you have to reactivate the plugin. In production environments that would be critical if you, as a merchant, forgetting that part.

If you've gone this far, searching and replacing is the last action to make: 

    mv init.php resursbankgateway.php
    sed -i 's/Plugin Name: Tornevall Networks Resurs Bank payment gateway/Plugin Name: Resurs Bank Payment Gateway/' \
        resursbankgateway.php readme.txt
    sed -i 's/= Tornevall Networks Resurs Bank payment gateway/= Resurs Bank Payment Gateway/' \
        resursbankgateway.php readme.txt
    sed -i 's/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/resurs-bank-payment-gateway-for-woocommerce/' \
        *.php includes/Resursbank/*.php includes/Resursbank/Helpers/*.php

And for the language, this bash sequence should do just fine, as the language domain differs between the repos: 

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
