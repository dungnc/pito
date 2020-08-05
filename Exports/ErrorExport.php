<?php 
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ErrorExport implements FromArray
{
    protected $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    public function array(): array
    {
        return $this->errors;
    }
}