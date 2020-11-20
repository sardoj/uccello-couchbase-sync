<?php

namespace Uccello\UccelloCouchbaseSync\Support\Traits;

use Illuminate\Support\Facades\Schema;
use Uccello\Core\Models\Entity;
use Uccello\Core\Models\Module;

trait HandlesCouchbaseChange
{
    protected function handleCouchbaseDocumentChange($document)
    {
        // If ucid is not defined => create
        // else if deleted == true => delete
        // else update

        if (empty($document->_deleted) && empty($document->ucid)) {
            // Create
            $this->createRecord($document);
        } else {
            $record = $this->getRecordFromDocument($document);

            if ($record) {
                if (isset($document->_deleted) && $document->_deleted === true) {
                    // Delete
                    $this->deleteRecord($record, $document);
                } else {
                    // Update
                    $this->updateRecord($record, $document);
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

    protected function getRecordFromDocument($document)
    {
        $record = null;

        // If ucuuid is set, search by couchbase_id or uuid
        if (isset($document->ucuuid)) {
            $entity = Entity::where('couchbase_id', $document->_id)
                ->orWhere('id', $document->ucuuid)
                ->first();
        } else {
            $entity = Entity::where('couchbase_id', $document->_id)->first();
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

    protected function createRecord($document)
    {
        // Checks ucmodule is defined
        if (!isset($document->ucmodule)) {
            return;
        }

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

        // Increments counter
        if (property_exists($this, 'createCpt')) {
            $this->createCpt++;
        }

        return $record;
    }

    protected function updateRecord($record, $document)
    {
        // Checks if ucmodule is defined
        if (!isset($document->ucmodule)) {
            return;
        }

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

        // Increments counter
        if (property_exists($this, 'updateCpt')) {
            $this->updateCpt++;
        }

        return $record;
    }

    protected function deleteRecord($record, $document)
    {
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

        // Increments counter
        if (property_exists($this, 'deleteCpt')) {
            $this->deleteCpt++;
        }
    }
}
