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

    /**
     * Boot the trait and add the model events to synchronize with firebase
     */
    public static function bootSyncsWithCouchbase()
    {
        static::created(function ($model) {
            $model->saveToCouchbase('set');
        });
        static::updated(function ($model) {
            $model->saveToCouchbase('update');
        });
        static::deleted(function ($model) {
            $model->saveToCouchbase('delete');
        });
        static::restored(function ($model) {
            $model->saveToCouchbase('set');
        });
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
                $_id = $entity->data->couchbase->_id ?? '';
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
                $_id = $entity->data->couchbase->_rev ?? '';
            }
        }

        return $_id;
    }

    /**
     * @return array
     */
    protected function getCouchbaseSyncData()
    {
        if ($fresh = $this->fresh()) {
            // Add module name
            unset($fresh->{$this->getKeyName()}); // Remove id (could create conflicts with Couchbase Lite)
            $fresh->ucid = $this->getKey(); // Add id name ucid
            $fresh->ucmodule = $this->module->name ?? '';

            // Add Couchbase _id and _rev if exists
            $_id = $this->couchbaseId;
            $_rev = $this->couchbaseRev;

            if ($_id && $_rev) {
                $fresh->_id = $_id;
                $fresh->_rev = $_rev;
            }

            return $fresh->toArray();
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
        } elseif ($mode === 'update') {
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
                $data = (array) $entity->data ?? [];
                $data['couchbase'] = [
                    '_id' => $_id,
                    '_rev' => $_rev
                ];
                $entity->data = $data;
                $entity->save();
            }
        }
    }
}
