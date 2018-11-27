# Resurs Bank for WooCommerce

In the initial days of Resurs ecommerce a plugin was developed to support Resurs Bank in the gateway-verse of WooCommerce. It was, just like this one, released as third party edition for Resurs Bank payment flows.

When time passed, the source code got a bit unhandy to develop with, but since the code was already sent to production environments it was also dependent on functionality. Breaking something in this state is basically not good for business.

This version of the plugin is a rebuild-fork of the prior one, and a try to free some bottlenecks.

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

### What should be done

* Administration interface must be pushed out as a primary goal as we are dependent on dynamic configuration
* Warn when prior version of the plugin is running the same as this module
* Filters and hooks should be covered in as many sections as possible
* Multiple countries (Select flow per country)
* Are you running multiple credentials, make sure salt keys for callbacks are separated for each section
* Payment methods should be dynamically loaded and configured

