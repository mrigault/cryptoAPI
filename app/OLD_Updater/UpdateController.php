<?php

namespace App\Updater;

use Laravel\Lumen\Routing\Controller as BaseController;
use Log;

/**
 * Run from the CLI
 * php artisan ExchangeUpdater
 */

class UpdateController extends BaseController
{

    /**
     * Construct SchemeController
     */
    public function __construct() {

    }

    /**
     * Updates all coins with prices
     * -- creates a history.json file for reading via app
     * --- Fills gaps where needed in history.json
     * --- Adds new average price to history.json
     * Infinite loop -- should be fired as much as possible
     */
    protected function updateCoinPrices() {

        // get all active coins

        // foreach coin

          // check if there are gaps in history

            // Fill gaps where needed

          // get exchanges related to coin

            // foreach exchange get current price

              // if 404 current price, deactivate relation

            // calculate average price

        // create json file with filled gaps and new average price

    }

    /**
     * Checks the active coin list of each active installed exchange
     * -- Activates / deactivates coins based on relation
     * Should be fired once every day
     */
    protected function checkExchangesCoinList() {

        // get all active exchanges

          // foreach exchanges get the coin list

            // If can't get coin list, deactivate the exchange

            // Check if coin needs to update its relation

    }

    /**
     * Updates the exchanges
     * MOVED TO OLD SINCE 22-04-17
     */
    protected function UpdateExchanges() {

        $exchanges = array('CoinCap');

        foreach($exchanges as $exchangeName) {

          $stringPath = '/Exchanges/'.$exchangeName.'/'.$exchangeName . '.php';
          $path       = __DIR__ . $stringPath;

          if(!file_exists($path)) {
              
              // error logging 
              // active exchange, maar heeft geen class
              Log::warning('Active exchange, but no class exists.', ['exchange' => $exchangeName]);
              continue;
          }

          $class = 'App\\Updater\\Exchanges\\'.$exchangeName.'\\'.$exchangeName;
          $class = new $class;
          $class->updater();

        }

    }

}