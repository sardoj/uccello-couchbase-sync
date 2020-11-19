<?php

namespace Uccello\UccelloCouchbaseSync\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Uccello\Core\Models\Module;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $syncedModules = $this->getModulesSyncedWithCouchbase();

        foreach ($syncedModules as $module) {
            $this->syncModuleData($module);
        }
    }

    protected function getModulesSyncedWithCouchbase()
    {
        $syncedModules = collect();

        $modules = Module::whereNotNull('model_class')->get();
        foreach ($modules as $module) {
            $modelClass = $module->model_class;

            if (class_exists($modelClass) && in_array('Uccello\UccelloCouchbaseSync\Support\Traits\SyncsWithCouchbase', class_uses($modelClass))) {
                $syncedModules[] = $module;
            }
        }

        return $syncedModules;
    }

    protected function syncModuleData(Module $module)
    {
        // Get last sync datetime
        $lastSyncDate = $this->getLastSyncDatetime($module);

        // Memorize new datetime before to get records (important for not forgetting new modifications the next time)
        $newSyncDate = Carbon::now();

        // Syncs created records
        $createdRecords = $this->getCreatedRecords($module, $lastSyncDate);
        $this->createRecords($module, $createdRecords);
        unset($createdRecords); // Improves memory

        // Syncs updated records
        $updatedRecords = $this->getUpdatedRecords($module, $lastSyncDate);
        $this->updateRecords($module, $updatedRecords);
        unset($updatedRecords); // Improves memory

        // Syncs deleted records
        $deletedRecords = $this->getDeletedRecords($module, $lastSyncDate);
        $this->deleteRecords($module, $deletedRecords);
        unset($deletedRecords); // Improves memory

        // Update last sync datetime
        $this->setLastSyncDatetime($module, $newSyncDate);
    }

    protected function getLastSyncDatetime(Module $module)
    {
        return $module->data->couchbase_last_sync ?? null;
    }

    protected function getCreatedRecords(Module $module, $lastSyncDate)
    {
        //TODO: Get all record from Couchbase with "created_at" > $lastSyncDate
        // Check created_at column name in config file
        return null;
    }

    protected function getUpdatedRecords(Module $module, $lastSyncDate)
    {
        //TODO: Get all record from Couchbase with "updated_at" > $lastSyncDate
        // Check updated_at column name in config file
        return null;
    }

    protected function getDeletedRecords(Module $module, $lastSyncDate)
    {
        //TODO: Get all record from Couchbase with "deleted_at" > $lastSyncDate
        // Check deleted_at column name in config file
        return null;
    }

    protected function createRecords(Module $module, $records)
    {
        //TODO: Create records
    }

    protected function updateRecords(Module $module, $records)
    {
        //TODO: Update records
    }

    protected function deleteRecords(Module $module, $records)
    {
        //TODO: Delete records
    }

    protected function setLastSyncDatetime(Module $module, $newSyncDate)
    {
        $data = (array) $module->data || [];
        $data['couchbase_last_sync'] = $newSyncDate->format('Y-m-d H:i:s');
        $module->data = $data;
        $module->save();
    }
}
