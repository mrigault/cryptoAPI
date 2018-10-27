<?php

namespace App\Updater\Exchanges\Bittrex;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Coin;

use Carbon\Carbon;

class Bittrex {

	/**
	 * Useable commands
	 * @var array available commands
	 */
	protected $commands = array('UpdateCoinList','UpdateCoinPrices');

	/**
	 * Exchange default name
	 * @var string exchange name 
	 */
	protected $exchangeName = 'Bittrex';

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
	public $APIUrl				= 'https://bittrex.com/api/v1.1/public/';

	/**
	 * Constructor
	 * Check wheter exchange is installed and active
	 */
	public function __construct() {

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

		$this->AddTheseCoins($NewCoins);

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

			if($Coin) {
				
				try {
					// Detach and update
					$detach 			= $Coin->exchanges()->detach($this->exchangeID);

					// deactivate coin if needed
					if(!$Coin->hasAnyActiveRelationship($Coin->id)) {
						$deactivateCoin = $Coin->update(['active' => 0]);
					}

					// $updateCoin 		= $Coin->update(['updated_at' => Carbon::now()]);
					$updateExchange 	= DB::table('exchanges')
					            	  		  ->where('name', $this->exchangeName)
					            	  	  	  ->update(['updated_at' => Carbon::now()]);

					// Update log met successes

				} catch(Exception $e) {
					// error msg here
				}

			} else {

				// Coin bestaat niet, maybe some error logging hier
				continue;

			}
		}
	}

	protected function getActiveCoinList() {
		$currenciesURL = $this->APIUrl . 'getmarkets';

		// get JSON currencies from Bittrex
		$rawJSON = file_get_contents($currenciesURL);
		$JSON = json_decode($rawJSON);

		$JSON = array_filter($JSON->result, array($this, 'getActiveCoinsFromJSON'));

		// create a readable array for Exchange Model
		$readableArray = array();

		$i = 0;
		foreach($JSON as $obj) {

			$readableArray[$i]['shortname'] = $obj->MarketCurrency;
			$readableArray[$i]['fullname'] = $obj->MarketCurrencyLong;
			$i++;
		}

		// Delete duplicates by shortname
		$readableArray = $this->checkForDuplicatesByName($readableArray);

		// return readableArray;
		return $readableArray;
	}

	/*
	 * API METHODS
	 * Helper function to filter JSON array coins
	 * @param return where isActive = true
	 */
	protected function getActiveCoinsFromJSON($obj) {
		return $obj->IsActive == true;
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

	/**
	 * Adds or updates new coins
	 * @param array $NewCoins array of coins 
	 */
	protected function AddTheseCoins($NewCoins) {

		foreach($NewCoins as $coin) {

			dd($coin);

		}

	}

	/**
	 * Update prices of the coins associated with the exchange
	 */
	protected function UpdateCoinPrices() {
		//echo 'updatecoinprices for ' . $this->exchangeName;
		return;
	}

}