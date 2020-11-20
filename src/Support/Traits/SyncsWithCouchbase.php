<?php

namespace Uccello\UccelloCouchbaseSync\Support\Traits;

use Uccello\Core\Models\Entity;
use Uccello\UccelloCouchbaseSync\Support\Classes\CouchbaseSync;

trait SyncsWithCouchbase
{
    /**
     * @var CouchbaseInterface|null
     */
    protected $couchbaseClient;

    public $forceCouchbaseUpdate = false;
    public $stopCouchbaseDeleteEvent = false;

    public $couchbaseId;
    public $couchbaseRev;

    /**
     * Boot the trait and add the model events to synchronize with firebase
     */
    public static function bootSyncsWithCouchbase()
    {
        static::created(function ($model) {
            if ($model->forceCouchbaseUpdate) { // See SyncsFromCouchbase.php -> createRecord()
                $model->saveToCouchbase('update');
            } else {
                $model->saveToCouchbase('set');
            }
        });

        static::updated(function ($model) {
            $model->saveToCouchbase('update');
        });

        // If it is a true delete, it is important to use "deleting" instead of "deleted"
        // else we don't know record data
        static::deleting(function ($model) {
            if ($model->stopCouchbaseDeleteEvent === true) { // See SyncsFromCouchbase.php -> deleteRecord()
                return;
            }

            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                $model->saveToCouchbase('soft-delete');
            } else {
                $model->saveToCouchbase('delete');
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->saveToCouchbase('set');
            });
        }
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        // if ($this->_couchbase_id) {
        //     $this->couchbase_id = $this->_couchbase_id;
        //     $this->couchbase_rev = $this->_couchbase_rev;
        //     unset($this->_couchbase_id);
        //     unset($this->_couchbase_rev);
        // }

        parent::save($options);
    }

    /**
     * Returns couchbase _id.
     *
     * @return string|null
     */
    public function getCouchbaseIdAttribute()
    {
        $_id = null;

        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $_id = $entity->couchbase_id;
            }
        }

        return $_id;
    }

    /**
     * Returns couchbase _rev.
     *
     * @return string|null
     */
    public function getCouchbaseRevAttribute()
    {
        $_id = null;

        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $_id = $entity->couchbase_rev;
            }
        }

        return $_id;
    }

    /**
     * Sets couchbase _id.
     *
     * @return string|null
     */
    public function setCouchbaseId($_id)
    {
        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $entity->couchbase_id = $_id;
                $entity->save();
            }
        }
    }

    /**
     * Sets couchbase _rev.
     *
     * @return string|null
     */
    public function setCouchbaseRev($_rev)
    {
        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $entity->couchbase_rev = $_rev;
                $entity->save();
            }
        }
    }

    /**
     * Sets couchbase _id and _rev.
     *
     * @return string|null
     */
    public function setCouchbaseIdAndRev($_id, $_rev)
    {
        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $entity->couchbase_id = $_id;
                $entity->couchbase_rev = $_rev;
                $entity->save();
            }
        }
    }

    /**
     * @return array
     */
    protected function getCouchbaseSyncData()
    {
        if ($fresh = $this->fresh()) {
            // Add module name
            $fresh->ucid = $this->getKey(); // Add id name ucid
            $fresh->ucuuid = $this->uuid; // Add uuid
            $fresh->ucmodule = $this->module->name ?? '';

            // Add Couchbase _id and _rev if exists
            $_id = $this->couchbaseId ?? $this->couchbase_id;
            $_rev = $this->couchbaseRev ?? $this->couchbase_rev;

            if ($_id && $_rev) {
                $fresh->_id = $_id;
                $fresh->_rev = $_rev;
            }

            $freshArray = $fresh->toArray();
            unset($freshArray[$this->getKeyName()]); // Remove id (could create conflicts with Couchbase Lite)
            unset($freshArray['uuid']);

            return $freshArray;
        }
        return [];
    }

    /**
     * @param $mode
     */
    protected function saveToCouchbase($mode)
    {
        if (is_null($this->couchbaseClient)) {
            $this->couchbaseClient = new CouchbaseSync(config('couchbase.database_url'), config('couchbase.secret'));
        }

        $couchbaseSyncData = $this->getCouchbaseSyncData();

        $path = !empty($couchbaseSyncData["_id"]) ? '/'.$couchbaseSyncData["_id"] : '/';

        if ($mode === 'set') {
            $return = $this->couchbaseClient->push($path, $couchbaseSyncData);
        } elseif ($mode === 'update' || $mode === 'soft-delete') {
            $return = $this->couchbaseClient->update($path, $couchbaseSyncData);
        } elseif ($mode === 'delete') {
            $return = $this->couchbaseClient->delete($path, ['rev' => $couchbaseSyncData["_rev"]]);
        }

        if (!empty($return)) {
            $returnedData = json_decode($return);

            if ($returnedData->ok ?? false) {
                $_id = $returnedData->id ?? null;
                $_rev = $returnedData->rev ?? null;

                $this->__saveCouchbaseData($_id, $_rev);
            }
        }
    }

    private function __saveCouchbaseData($_id, $_rev)
    {
        $module = $this->module;

        if ($module) {
            $entity = Entity::where('module_id', $module->getKey())
                ->where('record_id', $this->getKey())
                ->first();

            if ($entity) {
                $entity->couchbase_id = $_id;
                $entity->couchbase_rev = $_rev;
                $entity->save();
            }
        }
    }
}
