<?php

namespace App\Traits;

trait AllTraits
{
    use FileManager, ChunkFileUpload, ImagePathTrait, ApiResponse, DatabaseExportable, HasFilter, UnitConverter, TableAction, HasLanguage, HasCurrency;

}
