<?php

namespace Uccello\UccelloCouchbaseSync\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Uccello\Core\Models\Entity;
use Uccello\Core\Models\Module;
use Uccello\UccelloCouchbaseSync\Models\CouchbaseParam;
use Uccello\UccelloCouchbaseSync\Support\Classes\CouchbaseSync;

class SyncsFromCouchbase extends Command
{
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

        // $syncedModules = $this->getModulesSyncedWithCouchbase();

        // foreach ($syncedModules as $module) {
        //     $this->syncModuleData($module);
        // }

        // $this->setLastSyncSequence();
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
            // IF ucid is not defined => create
            // else if deleted == true => delete
            // else update

            if (empty($change->deleted) && isset($change->doc) && empty($change->doc->ucid)) {
                // Create
                $this->createRecord($change);
            } else {
                $record = $this->getRecordFromChange($change);

                if ($record) {
                    if (isset($change->deleted) && $change->deleted === true) {
                        // Delete
                        $this->deleteRecord($record, $change);
                    } else {
                        // Update
                        $this->updateRecord($record, $change);
                    }
                }
            }
        }
    }

    protected function getModulesSyncedWithCouchbase()
    {
        $this->syncedModules = collect();

        $modules = Module::whereNotNull('model_class')->get();
        foreach ($modules as $module) {
            $modelClass = $module->model_class;

            if (class_exists($modelClass) && in_array('Uccello\UccelloCouchbaseSync\Support\Traits\SyncsWithCouchbase', class_uses($modelClass))) {
                $this->syncedModules[] = $module->name;
            }
        }

        return $this->syncedModules;
    }

    protected function getRecordFromChange($change)
    {
        $record = null;

        // If ucuuid is set, search by couchbase_id or uuid
        if (isset($change->doc) && isset($change->doc->ucuuid)) {
            $entity = Entity::where('couchbase_id', $change->id)
                ->orWhere('id', $change->doc->ucuuid)
                ->first();
        } else {
            $entity = Entity::where('couchbase_id', $change->id)->first();
        }

        if ($entity) {
            $module = ucmodule($entity->module_id);

            if ($module) {
                $modelClass = $module->model_class;
                $record = $modelClass::find($entity->record_id);
            }
        }

        return $record;
    }

    protected function createRecord($change)
    {
        // Checks if a document is defined
        if (!isset($change->doc) || !isset($change->doc->ucmodule)) {
            return;
        }

        // Gets document
        $document = $change->doc;

        // Checks if the module is synced with Couchbase
        if (!$this->syncedModules->contains($document->ucmodule)) {
            return;
        }

        // Gets module
        $module = ucmodule($document->ucmodule);
        if (!$module) {
            return;
        }

        // Creates new record from document data
        $modelClass = $module->model_class;
        $record = new $modelClass;

        foreach ($document as $key => $val) {
            if (Schema::hasColumn($record->getTable(), $key)) {
                $record->{$key} = $val;
            }
        }

        // It is important to force update instead of create
        // else Uccello will understand he must create a new record in Couchbase
        if ($document->_id && $document->_rev) {
            $record->forceCouchbaseUpdate = true;

            // Normally, couchbase_id and couchbase_rev are getted from uccello_entities
            // but those data don't exist before saving(). So we force them.
            $record->couchbaseId = $document->_id;
            $record->couchbaseRev = $document->_rev;
        }

        $record->save();

        $record->forceCouchbaseUpdate = false;


        return $record;
    }

    protected function updateRecord($record, $change)
    {
        if (!isset($change->doc) || !isset($change->doc->ucmodule)) {
            return;
        }

        // Gets document
        $document = $change->doc;

        // Checks if the module is synced with Couchbase
        if (!$this->syncedModules->contains($document->ucmodule)) {
            return;
        }

        foreach ($document as $key => $val) {
            if (Schema::hasColumn($record->getTable(), $key)) {
                $record->{$key} = $val;
            }
        }
        $record->save();

        // Update couchbase _rev
        if ($document->_rev) {
            $record->setCouchbaseRev($document->_rev);
        }

        return $record;
    }

    protected function deleteRecord($record, $change)
    {
        if (!isset($change->doc)) {
            return;
        }

        // Gets document
        $document = $change->doc;

        // It's important to stop delete event else Uccello will try
        // to delete record from couchbase
        $record->stopCouchbaseDeleteEvent = true;

        if (config('couchbase.force_delete')) {
            if (method_exists($record, 'trashed')) {
                $record->forceDelete();
            } else {
                $record->delete();
            }
        } else {
            $record->setCouchbaseRev($document->_rev);
            $record->delete();
        }

        $record->stopCouchbaseDeleteEvent = false;
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
