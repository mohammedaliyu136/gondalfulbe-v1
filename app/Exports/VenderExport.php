<?php

namespace App\Exports;

use App\Models\Vender;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VenderExport implements FromCollection, WithHeadings
{
    protected $cooperative_id;

    public function __construct($cooperative_id = null)
    {
        $this->cooperative_id = $cooperative_id;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Vender::where('created_by', \Auth::user()->creatorId());
        
        if ($this->cooperative_id) {
            $query->where('cooperative_id', $this->cooperative_id);
        }
        
        $data = $query->get();

        foreach($data as $k => $vendor)
        {
            unset($vendor->id,$vendor->password, $vendor->lang,$vendor->tax_number,
                $vendor->is_active, $vendor->avatar,$vendor->created_by,
                $vendor->email_verified_at, $vendor->remember_token,
                $vendor->document_paths,
                $vendor->created_at,$vendor->updated_at);
                $data[$k]["vender_id"]        = \Auth::user()->venderNumberFormat($vendor->vender_id);
                $data[$k]["balance"]          = \Auth::user()->priceFormat($vendor->balance);
                $data[$k]["cooperative_id"]   = $vendor->cooperative_id ? \Modules\Cooperatives\Models\Cooperative::find($vendor->cooperative_id)?->name : '';
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            "Vendor No",
            "Name",
            "Email",
            "Contact",
            "Billing Name",
            "Billing Country",
            "Billing State",
            "Billing City",
            "Billing Phone",
            "Billing Zip",
            "Billing Address",
            "Shipping Name",
            "Shipping Country",
            "Shipping State",
            "Shipping City",
            "Shipping Phone",
            "Shipping Zip",
            "Shipping Address",
            "Balance",
            "Cooperative",
            "Gender",
            "Status",
            "Registration Date",
            "DOB",
            "Bank Name",
            "Account Number",
            "BVN",
            "GPS Coordinates",
            "Digital Payment Enabled"
        ];
    }
}
