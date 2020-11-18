<?php

Route::middleware('web', 'auth')
->namespace('Uccello\UccelloCouchbaseSync\Http\Controllers')
->name('uccello-couchbase-sync.')
->group(function() {

    // This makes it possible to adapt the parameters according to the use or not of the multi domains
    if (!uccello()->useMultiDomains()) {
        $domainParam = '';
        $domainAndModuleParams = '{module}';
    } else {
        $domainParam = '{domain}';
        $domainAndModuleParams = '{domain}/{module}';
    }

    // Example 1
    // {domain}/{module}/my_path => This route is available for all modules in all domains
    // Route::get($domainAndModuleParams.'/my_path', 'MyController@action')->name('my_path');

    // Example 2
    // {domain}/home/my_path => This route forces to use 'home' module and is available in all domains
    // Route::get($domainParam.'/home/my_path', 'MyController@action')->defaults('module', 'home')->name('home.my_path');

    // Put your routes here
});