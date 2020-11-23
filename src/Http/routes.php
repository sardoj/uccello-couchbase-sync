<?php

Route::middleware('web')
    ->namespace('Uccello\UccelloCouchbaseSync\Http\Controllers')
    ->name('uccello-couchbase-sync.')
    ->group(function () {
        Route::post('/couchbase/webhook/change', WebhookController::class)->name('couchbase.webhoot');
    });
