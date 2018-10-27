<?php

namespace App\Console\Commands;

use App\Coin;
use App\Events\ReportError;
use App\Events\TempError;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateCoinPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:CoinPrices {order}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all the coin prices, market cap and volume';

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

        // Get the order so we can divide the worker in different daemons
        $allowed = $this->getAllowedNames();

        $time_start = microtime(true); // excution time

        // get all active coins with its exchanges

        $coins = Coin::where('active', '1')
                       ->with(['exchanges' => function ($query) {
                            $query->where('exchanges.active', '1');
                         }])
                       ->get();

        $updated = 0;

        // get current BTC - USD Rate
        // Returns USD price of 1 BTC
        // 
        
        if(!$rate = @file_get_contents('https://coinbase.com/api/v1/prices/spot_rate')) {
            $error = array('name' => 'general-'.'no-converter-rate-found');
            event(new TempError($error));

            var_dump('Aborted! No converter rate found!');

            return false;
        }

        $rate = json_decode($rate)->amount;

        foreach($coins as $coin) {

            if(!$this->isCoinAllowed($coin->shortname,$allowed)) continue;

            // Hard kill if needed
            if(file_get_contents(__DIR__ . '/kill.txt') === 'STOP') die();

            var_dump('Updating ' .$coin->shortname);

            $prices = [];
            $marketcaps = [];
            $volumes = [];

            $storage = $this->getOrCreateCoinJSON($coin);
            $nodes  = [];
            $classes = [];
            $class = '';

            // setup nodes for each exchanges
            foreach($coin->exchanges as $exchange) {

                $classFile = __DIR__ . '/exchanges/'.$exchange->name.'.php';
                if(!file_exists($classFile)) {
                    $error = array(
                                'level' => 'Error',
                                'title' => 'Active exchange has no class',
                                'report' => 'Trying to access file: '.$classFile.', but it does not exist. This class needs to exist in order to get the current price!',
                                'active' => 1
                             );
                    event(new ReportError($error));
                    continue;
                }

                $class = 'App\\Console\\Commands\\exchanges\\'.$exchange->name;

                // If class returns false
                if(!$CoinJsonFile = $class::getCoinJsonFile($coin->shortname)) continue;

                $nodes[] = $CoinJsonFile;
                $classes[] = $class;
            }

            $coinJsonFiles = $this->multiple_threads_request($nodes);

            // get average price from the json files
            $threadCounter = 0;
            foreach($coinJsonFiles as $file) {

                $class = $classes[$threadCounter];
                $file  = json_decode($file);

                // get the current price
                $price = $class::getPriceFromFile($file,$rate,$coin->shortname);

                if(!$price) {
                    // Check coinlist for exchange and deactivate coin if needed
                    $error = array('name' => $coin->shortname . '-'.'no-price-found'.'-'.$class);
                    event(new TempError($error));
                    
                } else {
                    $prices[] = $price;
                }

                // get the current market cap
                $marketcap = $class::getMarketCapFromFile($file,$coin->shortname);
                if($marketcap) $marketcaps[] = $marketcap;

                $volume = $class::getVolumeFromFile($file,$coin->shortname);
                if($volume) $volumes[] = $volume;

                // get the current volume

                $threadCounter++;
            }

            // create json object for storage
            $averagePrice = ''; $averageMarketCap = ''; $averageVolume = '';
            if(!empty($prices)) $averagePrice = array_sum($prices) / count($prices);
            if(!empty($marketcaps)) $averageMarketCap = array_sum($marketcaps) / count($marketcaps);
            if(!empty($volumes)) $averageVolume = array_sum($volumes) / count($volumes);

            $jsonObject = array(
                            'price' => $averagePrice,
                            'marketcap' => $averageMarketCap,
                            'volume' => $averageVolume
                          );

            if(empty($jsonObject['price']) && empty($jsonObject['marketcap']) && empty($jsonObject['volume'])) {
                $error = array('name' => $coin->shortname . '-'.'no-values-added-to-json'.'-'.$class);
                return event(new TempError($error));
            } 

            $json = json_decode(Storage::get($storage),true);
            $json[round(microtime(true) * 1000)] = $jsonObject;

            ksort($json);
            Storage::put($storage, json_encode($json));

            $updated++;

        }
        
        $time_end = microtime(true);
        var_dump('Updated ('.$updated.') coins - updated at : '.Date('d F, Y H:i:s').' it took: '. ($time_end - $time_start) .' seconds');
        // Log::info('Updated ('.$updated.') coins - updated at : '.Date('d F, Y H:i:s').' it took: '. ($time_end - $time_start) .' seconds');
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

    /**
     * Creates a folder structure for a coin
     */
    protected function createFolderStructure($coin) {

        if($folder = Storage::makeDirectory("coins/_{$coin->shortname}")) {
            return true;
        }

        return false;

    }

    /**
     * Creates a multithread
     */
    protected function multiple_threads_request($nodes){
        $mh = curl_multi_init();
        $curl_array = array();
        foreach($nodes as $i => $url)
        {
            $curl_array[$i] = curl_init($url);
            curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_array[$i], CURLOPT_CONNECTTIMEOUT , 3);
            curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 3);
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

    protected function getAllowedNames() {

        $allowed = [];
        $order = $this->argument('order');

        if($order == 1) {
            $allowed = ['numbers','a'];
        } elseif($order == 2) {
            $allowed = ['b','c'];
        } elseif($order == 3) {
            $allowed = ['d','e','f'];
        } elseif($order == 4) {
            $allowed = ['g','h','i'];
        } elseif($order == 5) {
            $allowed = ['j','k','l'];
        } elseif($order == 6) {
            $allowed = ['m','n','o'];
        } elseif($order == 7) {
            $allowed = ['p','q','r'];
        } elseif($order == 8) {
            $allowed = ['s','t','u'];
        } elseif($order == 9) {
            $allowed = ['v','w','x'];
        } elseif($order == 10) {
            $allowed = ['y','z'];
        }

        return $allowed;

    }

    protected function isCoinAllowed($coin,$allowed) {

        $first_letter = strtolower( $coin[0] );

        if($allowed[0] == 'numbers') {
            if(is_numeric($first_letter)) return true;
            if($first_letter == 'a') return true;
        } else {
            if(in_array($first_letter, $allowed)) return true;
        }

        return false;
    }

}
