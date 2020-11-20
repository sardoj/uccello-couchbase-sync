<?php

namespace Uccello\UccelloCouchbaseSync\Console\Commands;

use Illuminate\Console\Command;
use Uccello\UccelloCouchbaseSync\Models\CouchbaseParam;
use Uccello\UccelloCouchbaseSync\Support\Classes\CouchbaseSync;
use Uccello\UccelloCouchbaseSync\Support\Traits\HandlesCouchbaseChange;

class SyncsFromCouchbase extends Command
{
    use HandlesCouchbaseChange;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'couchbase:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs data from a Couchbase database.';

    /**
     * @var CouchbaseInterface|null
     */
    protected $couchbaseClient;

    /**
     * List of names of modules synced with Couchbase
     *
     * @var Collection
     */
    protected $syncedModules;

    /**
     * List of changes
     *
     * @var StdObject|null
     */
    protected $changesData;

    protected $createCpt = 0;
    protected $updateCpt = 0;
    protected $deleteCpt = 0;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->getModulesSyncedWithCouchbase();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Create a Curl client able to connect with Couchbase Server
        $this->couchbaseClient = new CouchbaseSync(config('couchbase.database_url'), config('couchbase.secret'));

        // Get list of last changes
        $this->getLastChanges();

        // Handle changes
        $this->handleChanges();

        // Display information
        $this->info('Created: <comment>' . $this->createCpt . '</comment>');
        $this->info('Updated: <comment>' . $this->updateCpt . '</comment>');
        $this->info('Deleted: <comment>' . $this->deleteCpt . '</comment>');

        // Save new last sync sequence
        $this->setLastSyncSequence();
    }

    protected function getLastChanges()
    {
        // Get last sequence
        $lastSyncSeq = $this->getLastSyncSequence();

        // Request list of last changes
        $response = $this->couchbaseClient->get("_changes", ['include_docs' => "true", 'since' => $lastSyncSeq]);
        $this->changesData = json_decode($response);

        return $this->changesData;
    }

    protected function handleChanges()
    {
        if (!$this->changesData || !$this->changesData->results) {
            return;
        }

        foreach ($this->changesData->results as $change) {
            if ($change->doc) {
                $this->handleCouchbaseDocumentChange($change->doc);
            }
        }
    }

    protected function getLastSyncSequence()
    {
        $param = CouchbaseParam::where('key', 'last_sync_seq')->first();

        return $param->value ?? null;
    }

    protected function setLastSyncSequence()
    {
        $lastSyncSeq = $this->changesData->last_seq ?? null;

        $param = CouchbaseParam::where('key', 'last_sync_seq')->first();

        if (!$param) {
            $param = new CouchbaseParam;
            $param->key = 'last_sync_seq';
        }
        $param->value = $lastSyncSeq;
        $param->save();
    }
}
