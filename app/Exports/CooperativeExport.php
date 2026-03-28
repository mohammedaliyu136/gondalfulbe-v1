<?php

namespace App\Exports;

use Modules\Cooperatives\Models\Cooperative;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CooperativeExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $data = Cooperative::all();

        foreach($data as $k => $coop)
        {
            unset(
                $coop->id,
                $coop->created_at,
                $coop->updated_at
            );
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            "Name",
            "Location",
            "Leader Name",
            "Leader Phone",
            "Formation Date",
            "Average Daily Supply"
        ];
    }
}
