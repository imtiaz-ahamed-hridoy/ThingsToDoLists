<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

Trait TableAction
{

   public function deleteConfirm($id): void
   {
       $this->dispatch("deleteConfirm",$id);
   }

    #[On('delete')]
    public function delete($id): void
    {
        try {
            $record = $this->model::find($id);
            $files = $this->deleteFilesColumn ?? [];
            foreach ($files as $f) {
                if ($record->{$f}) {
                    $cleanPath = ltrim($record->{$f}, '/');
                    if (strpos($cleanPath, 'storage/') === 0) {
                        $cleanPath = substr($cleanPath, 8);
                    }
                    if (Storage::disk('public')->exists($cleanPath)) {
                        Storage::disk('public')->delete($cleanPath);
                    }
                }
            }
            if ($record) {
                $record->delete();
                flash()->success("Record deleted successfully");
            }else{
                flash()->error("Record not found");
            }
        }catch (\Exception $exception){
            flash()->error($exception->getMessage());
        }
    }

    public function statusChange($id): void
    {
        try {
            $record = $this->model::find($id);
            if (isset($this->statusChangeValue)) {
                $record->update([
                    'status' => array_search(!$this->statusChangeValue[$record->status], $this->statusChangeValue)
                ]);
                flash()->success("Record status changed successfully");
            }else{
                flash()->error("Set public property statusChange with boolean value and must be array");
            }
        }catch (\Exception $exception){
            flash()->error($exception->getMessage());
        }
    }
}
