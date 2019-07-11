<?php

namespace App\Http\Controllers\Warehouse;

// Main of Base Controller
use App\Http\Controllers\Controller;

// Embed a model

// Embed a Helper


class MainController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function index(){
        return date("Y-m-d H:i:s");
    }

    public function grid(){
        $data = [
            "meta"=> [
                "page"=> 1,
                "pages"=> 1,
                "perpage"=> 10,
                "total"=> 1,
                "sort"=> "asc",
                "field"=> "RecordID"
            ],
            "data"=> [
                [
                    "RecordID"=> 1,
                    "OrderID"=> "64980-196",
                    "Country"=> "Croatia",
                    "ShipCountry"=> "HR",
                    "ShipCity"=> "Vinica",
                    "ShipName"=> "Gutkowski LLC",
                    "ShipAddress"=> "0 Elka Street",
                    "CompanyEmail"=> "hkite7@epa.gov",
                    "CompanyAgent"=> "Hazlett Kite",
                    "CompanyName"=> "Streich LLC",
                    "Currency"=> "HRK",
                    "Notes"=> "fusce lacus purus aliquet at feugiat non pretium quis lectus suspendisse potenti in eleifend",
                    "Department"=> "Automotive",
                    "Website"=> "accuweather.com",
                    "Latitude"=> 46.339513099999998,
                    "Longitude"=> 16.1537893,
                    "ShipDate"=> "8/5/2016",
                    "PaymentDate"=> "2017-04-29 22:07:06",
                    "TimeZone"=> "Europe/Zagreb",
                    "TotalPayment"=> "$1162836.25",
                    "Status"=> 6,
                    "Type"=> 1,
                    "Actions"=> null
                ]
            ]
        ];
        return response()->json($data,200);
    }

}