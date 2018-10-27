<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

class FileController extends Controller
{
    public function __construct()
    {

    }

    public static function getFile($coin_name){

        $path = storage_path() . '/app/coins/_' . $coin_name . '/history_price.json';

        if(file_exists($path)) {
            $json_string = json_decode(file_get_contents($path),true);
            return $json_string;
        }
        else
            abort(404);
    }
}