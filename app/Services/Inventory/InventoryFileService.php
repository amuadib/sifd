<?php

namespace App\Services\Inventory;

use App\Models\InventoryFile;
use Illuminate\Support\Facades\DB;

class InventoryFileService
{
    /**
     * Delete an inventory record and its metadata/locations from the catalog database.
     *
     * STRICT READ-ONLY POLICY:
     * This ONLY removes the catalog entries (inventory_files, file_locations, file_tag).
     * Physical original files/folders on disk or network storage remain completely untouched.
     */
    public function deleteFromCatalog(InventoryFile $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->locations()->delete();
            $file->tags()->detach();
            $file->forceDelete();
        });
    }
}
