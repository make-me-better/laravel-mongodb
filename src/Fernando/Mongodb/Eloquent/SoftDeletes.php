<?php

namespace Fernando\Mongodb\Eloquent;

trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function forceDelete(array $options = [])
    {
        $this->forceDeleting = true;

        // Transaction
        if(!isset($options["session"]) && isset($_ENV["MDBSession"]))
            $options["session"] = $_ENV["MDBSession"];
        
        return tap($this->delete($options), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }
        });
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return mixed
     */
    protected function performDeleteOnModel(array $options = [])
    {
        
        // Transaction
        if(!isset($options["session"]) && isset($_ENV["MDBSession"]))
            $options["session"] = $_ENV["MDBSession"];
        
        if ($this->forceDeleting) {
            $this->exists = false;

            return $this->setKeysForSaveQuery($this->newModelQuery())->forceDelete($options);
        }

        return $this->runSoftDelete($options);
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function runSoftDelete(array $options = [])
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->timestamps && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        // Transaction
        if(!isset($options["session"]) && isset($_ENV["MDBSession"]))
            $options["session"] = $_ENV["MDBSession"];
        
        $query->update($columns, $options);
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool|null
     */
    public function restore(array $options = [])
    {
        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        // Once we have saved the model, we will fire the "restored" event so this
        // developer will do anything they need to after a restore operation is
        // totally finished. Then we will return the result of the save call.
        $this->exists = true;

        // Transaction
        if(!isset($options["session"]) && isset($_ENV["MDBSession"]))
            $options["session"] = $_ENV["MDBSession"];
        
        $result = $this->save($options);

        $this->fireModelEvent('restored', false);

        return $result;
    }
    
    /**
     * @inheritdoc
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
}
