<?php

namespace App\Http\Controllers;

use App\Coin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Lumen\Routing\Controller as BaseController;

class HistoryController extends BaseController
{
  	
  	private $secret;

	public function __construct() {
		$this->secret = 'XVfn9HRgkjbtpPTMPwKujxNj';
	}
	
	/**
	 * Create history from input of access
	 * @param  Request request
	 * @return boolean
	 */
	public function createHistory(Request $request) {

		// Check secret
		if($request->input('secret') != 'XVfn9HRgkjbtpPTMPwKujxNj') return 'Key invalid';

		// Get coin ID
		$coin = Coin::find($request->coin);

		// Import history for coin
		$path = $this->getOrCreateCoinJSON($coin);

		$json = Storage::get($path);

		if(empty($json)) {
			$json = array();
		} else {
			$json = (array) json_decode($json);
		}

		$requestHistory = json_decode($request->input('history'));

		foreach($requestHistory as $line) {

			$year = substr($line[0], 0,4);
			$month = substr($line[0], 4,2);
			$day = substr($line[0], 6,2);
			$hour = substr($line[1], 0,2);
			$min = substr($line[1], 2,2);

			$price = $line[3];
			$price = str_replace(' ', '', $price);
			$price = floatval($price);
			
			$dateTime = \DateTime::createFromFormat('d-m-Y H:i', $day . '-' . $month . '-' . $year . ' ' . $hour . ':' . $min);
			
			if(!array_key_exists($dateTime->getTimestamp(), $json)) {
				$json[$dateTime->getTimestamp()] = $price;
			} else {
				$prevPrice 	= $json[$dateTime->getTimestamp()];
				$avgPrice	= (floatval($prevPrice) + floatval($price)) / 2;
				$json[$dateTime->getTimestamp()] = $avgPrice;
			}

		}

		ksort($json);
		Storage::put($path, json_encode($json));

		return 'History imported';
	}

	/**
     * Returns path for storage folder
     */
    protected function getOrCreateCoinJSON($coin) {
         // Check if folder structure exists else create
        $path = '/coins/_'.$coin->shortname.'/history_price.json';

        if(!Storage::disk('local')->exists($path)) {
            Storage::put($path, '');
        }

        return $path;

    }

}
