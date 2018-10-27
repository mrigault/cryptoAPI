<?php

namespace App\Console\Commands;

use App\Coin;
use App\Events\ReportError;
use App\Events\TempError;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdateAllHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:AllHistory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all history from coincap';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $time_start = microtime(true); // excution time

        $updated = 0;

        var_dump('Importing history started...');

        // get coinlist van coincap (http://www.coincap.io/coins)
        if(!$coinlist = $this->getCoinslistCoincap()) return false;

        // get app active coins
        $appActiveCoinlist = $this->getAppActiveCoins();
        //if(empty($appActiveCoinlist)) return false;

        // voor elke coin in list (http://www.coincap.io/history/BTC)
        foreach($coinlist as $coin) {
            //if(!in_array($coin, $appActiveCoinlist)) continue;
            
            var_dump('Importing ' .$coin. '...');
            if($this->updateHistoryForCoin($coin)) $updated++;
        }
        
        $time_end = microtime(true);

        var_dump('History updated for ('.$updated.') coins - updated at : '.Date('d F, Y H:i:s').' it took: '. ($time_end - $time_start) .' seconds');
       
       return true;
    }

    /**
     * [getCoinslistCoincap description]
     * @return [type] [description]
     */
    protected function getCoinslistCoincap() {
        $classFile = __DIR__ . '/exchanges/CoinCap.php';
        if(!file_exists($classFile)) {
            $error = array(
                        'level' => 'Error',
                        'title' => 'CoinCap has no class',
                        'report' => 'Trying to access file: '.$classFile.', but it does not exist. This class needs to exist in order to getCoinstlistCoincap!',
                        'active' => 1
                     );
            event(new ReportError($error));
            return false;
        }

        $class = 'App\\Console\\Commands\\exchanges\\CoinCap';
        $JSON = $class::getActiveCoinlist();

        if(!$JSON) {
            $error = array(
                        'level' => 'Error',
                        'title' => 'CoinCap active coinlist not found',
                        'report' => 'Trying to curl active coinlist from coincap while importing history, but it could not be retrieved.',
                        'active' => 1
                     );
            event(new ReportError($error));
            return false;
        }

        return $JSON;

    }

    protected function getAppActiveCoins() {
        $return = array();
        $coins = Coin::where('active', '1')
                       ->with(['exchanges' => function ($query) {
                            $query->where('exchanges.active', '1');
                         }])
                       ->pluck('shortname')->toArray();
        return $coins;
    }

    protected function updateHistoryForCoin($coin) {
        if(!$coin) return false;

        $storage = $this->createStorage($coin);

        if(!$storage) {
            var_dump('Storage already exists, skipping ' .$coin);
            return false;
        }

        // get the history of the coin
        $coinHistoryUrl = 'http://www.coincap.io/history/'.strtoupper($coin);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $coinHistoryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $coinHistory = curl_exec($ch);
        curl_close($ch);

        if(!$coinHistory) {
            $error = array('name' => $coin . '-'.'no-history-for-coin-found'.'-'.$coin);
            event(new TempError($error));
            var_dump('Temp error created Trying to get the history for '.$coin);
            return false;
        }

        $coinHistory = json_decode($coinHistory);

        $marketCapHistory = $this->getMarketCapHistory($coin,$coinHistory);
        $priceHistory = $this->getPriceHistory($coin,$coinHistory);
        $volumeHistory = $this->getVolumeHistory($coin,$coinHistory);

        $JSON = $this->createJsonFromHistory($marketCapHistory, $priceHistory, $volumeHistory);
        if(!$JSON) {
            $error = array('name' => $coin . '-'.'no-JSON-object'.'-'.$coin);
            event(new TempError($error));
            var_dump('Temp error created No JSON Object could be created for '.$coin);
            return false;
        }

        ksort($JSON);

        Storage::put($storage, json_encode($JSON));

        // be sure to activate the coin
        $model = Coin::where('shortname',$coin)->first();
        if($model) $active = $model->activateCoin($model->id);
        

        return true;

    }

    /**
     * [getMarketCapHistory description]
     * @param  [type] $coin        [description]
     * @param  [type] $coinHistory [description]
     * @return [type]              [description]
     */
    protected function getMarketCapHistory($coin,$coinHistory) {
        if(!isset($coinHistory->market_cap)) {
            $error = array('name' => $coin . '-'.'no-getMarketCapHistory'.'-'.$coin);
            event(new TempError($error));
            var_dump('Temp error created getMarketCapHistory for '.$coin);
            return false;
        } 

        return $coinHistory->market_cap;

    }

    /**
     * [getMarketCapHistory description]
     * @param  [type] $coin        [description]
     * @param  [type] $coinHistory [description]
     * @return [type]              [description]
     */
    protected function getPriceHistory($coin,$coinHistory) {
        if(!isset($coinHistory->price)) {
            $error = array('name' => $coin . '-'.'no-getPriceHistory'.'-'.$coin);
            event(new TempError($error));
            var_dump('Temp error created getPriceHistory for '.$coin);
            return false;
        } 

        return $coinHistory->price;

    }

    /**
     * [getMarketCapHistory description]
     * @param  [type] $coin        [description]
     * @param  [type] $coinHistory [description]
     * @return [type]              [description]
     */
    protected function getVolumeHistory($coin,$coinHistory) {
        if(!isset($coinHistory->volume)) {
            $error = array('name' => $coin . '-'.'no-getVolumeHistory'.'-'.$coin);
            event(new TempError($error));
            var_dump('Temp error created getVolumeHistory for '.$coin);
            return false;
        } 

        return $coinHistory->volume;

    }

    protected function createJsonFromHistory($mcHistory, $priceHistory, $volumeHistory) {
        $JSON = array();

        // loop through market cap
        if(is_array($mcHistory)) {
            foreach($mcHistory as $item) {
                $JSON[$item[0]] = array('market_cap' => $item[1]);
            }
        }

        // loop through prices
        if(is_array($priceHistory)) {
            foreach($priceHistory as $item) {

                if(array_key_exists($item[0], $JSON)) {
                    $obj = $JSON[$item[0]];
                    $obj['price'] = $item[1];
                    $JSON[$item[0]] = $obj;
                    continue;
                }

                $JSON[$item[0]] = array('price' => $item[1]);
            }
        }

        // loop through volumes
        if(is_array($volumeHistory)) {
            foreach($volumeHistory as $item) {

                if(array_key_exists($item[0], $JSON)) {
                    $obj = $JSON[$item[0]];
                    $obj['volume'] = $item[1];
                    $JSON[$item[0]] = $obj;
                    continue;
                }

                $JSON[$item[0]] = array('volume' => $item[1]);
            }
        }

        if(empty($JSON)) return false;

        return $JSON;

    }

    /**
     * [getOrCreateCoinJSON description]
     * @param  [type] $coin [description]
     * @return [type]       [description]
     */
    protected function createStorage($coin) {
         // Check if folder structure exists else create
        $path = '/coins/_'.$coin.'/history_price.json';

        if(!Storage::disk('local')->exists($path)) {
            Storage::put($path, '');
            return $path;
        }

        return false;

    }

}
