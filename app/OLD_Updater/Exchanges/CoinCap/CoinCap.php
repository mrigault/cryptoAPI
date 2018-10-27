<?php

namespace App\Updater\Exchanges\CoinCap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Coin;

use Carbon\Carbon;
use Log;

// Curl
use App\Updater\Curl\Curl;

class CoinCap {

	/**
	 * Useable commands
	 * @var array available commands
	 */
	protected $commands = array('UpdateCoinList','UpdateCoinPrices');

	/**
	 * Exchange default name
	 * @var string exchange name 
	 */
	protected $exchangeName = 'CoinCap';

	private $exchangeID 	= null;

	/**
	 * Exchange class version
	 * @var string version number
	 */
	protected $version = '1.0';

	/**
	 * Public API URL
	 * @var string
	 */
	public $APIUrl				= 'http://socket.coincap.io/';

	protected $startTimer = 0;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->startTimer = microtime(true);
		Log::info('Started to update exchange: ' . $this->exchangeName . ' at ' . Carbon::now());

	}

	/**
	 * Updater function
	 * @return [type] [description]
	 */
	public function updater() {
		
		$exchange = DB::table('exchanges')->where('name', $this->exchangeName)->first();

		if($exchange) {

			$this->exchangeID = $exchange->id;

			/**
			 * Run the commands
			 */
			foreach($this->commands as $command) {
				$this->$command ();
			}

		} else {

			// maybe do some error logging

			return false;
		}

	}

	/**
	 * Update the coin list of the exchange
	 * Deactivates coins if needed
	 * Adds coins if needed
	 */
	protected function UpdateCoinList() {
		
		$ExchangeCoins = $this->getActiveCoinList();

		$ActiveCoins   = Coin::with('exchanges')
			                   ->whereHas('exchanges', function($q) {
			                       $q->where('exchanges.active', 1);
			                   })
			                   ->pluck('shortname')
			                   ->toArray();

		$NewCoins = array_filter($ExchangeCoins, function($a) use ($ActiveCoins) {
		    return ! in_array($a['shortname'], $ActiveCoins); 
		});

		$this->installTheseCoins($NewCoins);

		// Deactivate coins
		$DeactivateTheseCoins = array_diff($ActiveCoins, array_map(array($this, 'getShortOfArray'), $ExchangeCoins));

		$this->DeactivateTheseCoins($DeactivateTheseCoins);
		
	}

	protected function getShortOfArray($a){
	    return $a['shortname'];
	}

	/**
	 * Helper method to deactivate coins
	 * @param array $DeactivateTheseCoins coins that should be detached from the relationship
	 */
	protected function DeactivateTheseCoins($DeactivateTheseCoins) {
		foreach($DeactivateTheseCoins as $coin) {

			$Coin = Coin::where('shortname', $coin)->first();
			$successes = 0;

			if($Coin) {
				
				try {
					// Detach and update
					$detach 			= $Coin->exchanges()->detach($this->exchangeID);

					// deactivate coin if needed
					if(!$Coin->hasAnyActiveRelationship($Coin->id)) {
						$deactivateCoin = $Coin->update(['active' => 0]);
					}

					$updateExchange 	= DB::table('exchanges')
					            	  		  ->where('name', $this->exchangeName)
					            	  	  	  ->update(['updated_at' => Carbon::now()]);

					$successes++;

				} catch(Exception $e) {
					// error msg here
				}

			} else {

				Log::notice('Trying to deactivate a coin that doesnt exist.', [
						'status' => 'error',
						'error' => [
							'message' => 'Trying to deactivate a coin that doesnt exist',
							'status_code' => 400,
							'coin' => $Coin
						]
						
					]);
				continue;

			}
		}

	}

	protected function getActiveCoinList() {
		$currenciesURL = $this->APIUrl . 'front';

		// get JSON currencies from Bittrex
		$rawJSON = file_get_contents($currenciesURL);
		$JSON = json_decode($rawJSON);

		// create a readable array for Exchange Model
		$readableArray = array();

		$i = 0;
		foreach($JSON as $obj) {

			$readableArray[$i]['shortname'] = $obj->short;
			$readableArray[$i]['fullname'] = $obj->long;
			$readableArray[$i]['created_at'] = \Carbon\Carbon::now()->toDateTimeString();
			$readableArray[$i]['updated_at'] = \Carbon\Carbon::now()->toDateTimeString();
			$i++;
		}

		// Delete duplicates by shortname
		$readableArray = $this->checkForDuplicatesByName($readableArray);

		// return readableArray;
		return $readableArray;
	}

	/*
	 * API METHODS
	 * Helper function to remove duplicates by shortname
	 * @param return array without duplicates
	 */
	protected function checkForDuplicatesByName(array $array) {
		
		$newArr = array();
		foreach ($array as $val) {
		    $newArr[] = $val;    
		}
		$array = array_values($newArr);

		return $array;

	}

	/*
     * Install coins 
     * Already validated by previous methods
     * Input @array
     * return total installed
     */
    public function installTheseCoins(array $installTheseCoins) {

        //$coinIDs = DB::table('coins')->insertGetId([$installTheseCoins]);

        $coinIDs = array();
        foreach($installTheseCoins as $coin) {
            $coinIDs[] = DB::table('coins')->insertGetId($coin);
        }

        $newRelations = $this->createCoinsExchangeLove($coinIDs);

        // Here some error and success logging

    }

    /*
     * Creates love between the coins and the exchange 
     * Set's up relation in pivot table coin_exchange
     * Input @array coinIDS, @int exchangeID
     * return total new relations
     */
    public function createCoinsExchangeLove(array $coinIDs) {

        // Get the lover
        $exchange = \App\Exchange::find($this->exchangeID);

        foreach($coinIDs as $coin) {
            $exchange->coins()->attach($coin);
        }

        // Here some error and success logging

    }

	/**
	 * Update prices of the coins associated with the exchange
	 */
	protected function UpdateCoinPrices() {
		$this->updateHistory();
		return;
	}

	/**
	 * [multiple_threads_request description]
	 * @param  [type] $nodes [description]
	 * @return [type]        [description]
	 */
	protected function multiple_threads_request($nodes){
	    $mh = curl_multi_init();
	    $curl_array = array();
	    foreach($nodes as $i => $url)
	    {
	        $curl_array[$i] = curl_init($url);
	        curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
	        curl_multi_add_handle($mh, $curl_array[$i]);
	    }
	    $running = NULL;
	    do {
	        curl_multi_exec($mh,$running);
	    } while($running > 0);

	    $res = array();
	    foreach($nodes as $i => $url)
	    {
	        $res[$url] = curl_multi_getcontent($curl_array[$i]);
	    }

	    foreach($nodes as $i => $url){
	        curl_multi_remove_handle($mh, $curl_array[$i]);
	    }
	    curl_multi_close($mh);
	    return $res;
	}

	/**
	 * [updateHistory description]
	 * @return [type] [description]
	 */
	protected function updateHistory() {

		$successes = 0;
		$urls 	   = array();

		$ActiveCoins   	= Coin::with('exchanges')
			                   ->whereHas('exchanges', function($q) {
			                       $q->where('exchanges.active', 1);
			                   })
			                   ->pluck('shortname')
			                   ->toArray();

		foreach($ActiveCoins as $coin) {
			$history 		= $this->APIUrl . 'history/' . $coin;
    		$urls[] = $history;
		}

		$result = $this->multiple_threads_request($urls);
		$i = 0;

		if(!$result) {
			Log::critical('Critical error: Multiple threads request deliverd no results.', [
					'status' => 'error',
					'error' => [
						'message' => ' Multiple threads request deliverd no results',
						'status_code' => 404,
						'urls' => $urls
					]
					
				]);
			die();
		}

		foreach($result as $json) {
			$coin = $ActiveCoins[$i];

    		if(!$file = \Storage::put("coins/_{$coin}/{$this->exchangeName}/history.json", $json)) {

	    		// Deactivate the coin
    			// WRITE CODE FOR DEACTIVATING THE COIN HERE

	    		// Replace beaneath with correct error logging
	    		Log::critical('Critical error: file could not be made.', [
					'status' => 'error',
					'error' => [
						'message' => 'Failure making coin history. Could not create file for '. $coin,
						'status_code' => 400
					]
					
				]);
	    	}

	    	$successes++;
	    	$i++;
		}

    	$time_elapsed_secs = microtime(true) - $this->startTimer;

    	Log::info('CoinCap updated its coin history. History has been made', [
				'status' => 'success',
				'details' => [
					'successes' => $successes,
					'time' => $time_elapsed_secs
				]
				
			]);

	}


}