<?php

namespace Uccello\UccelloCouchbaseSync\Http\Controllers;

use Uccello\UccelloCouchbaseSync\Support\Traits\HandlesCouchbaseChange;

class WebhookController
{
    use HandlesCouchbaseChange;

    /**
     * List of names of modules synced with Couchbase
     *
     * @var Collection
     */
    protected $syncedModules;

    public function __invoke()
    {
        // Get post params
        $dataString = file_get_contents('php://input');

        // Get list of modules synced with Couchbase
        $this->getModulesSyncedWithCouchbase();

        // Parse json and get document
        $document = $dataString ? json_decode($dataString) : null;

        if ($document) {
            // Handle change
            $this->handleCouchbaseDocumentChange($document);
        }
    }
}
