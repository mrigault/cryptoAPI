<?php

namespace App\Console\Commands;

use App\Coin;
use App\Events\ReportError;
use App\Events\TempError;
use App\Exchange;
use Illuminate\Console\Command;

class UpdateExchangeCoinlist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:ExchangeCoinlist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks wheter coin relations should be updated';

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
        $time_start = microtime(true);
        var_dump('Checking coinlists started... Please wait.');

        $exchanges = Exchange::where('active', '1')->get();
        $coins_active = Coin::where('active', '1')
                           ->with(['exchanges' => function ($query) {
                                $query->where('exchanges.active', '1');
                           }])
                           ->get();

        $coins_deactive = Coin::where('active', '0')
                           ->with(['exchanges' => function ($query) {
                                $query->where('exchanges.active', '1');
                           }])
                           ->get();

        $activeCoinLists = $this->generateActiveCoinlist($exchanges);

        // CHeck coinlists
        if(!$activeCoinLists) {
            $error = array('name' => 'no-active-coinlist-found');
            event(new TempError($error));
            return false;
        }

        // Check if coins need deactivation
        $deactivated = $this->checkCoinsForDeactivation($coins_active, $activeCoinLists);

        // Check if coins need activation
        $activated = $this->checkCoinsForActivation($coins_deactive, $activeCoinLists);

        // display time
        $time_total = microtime(true) - $time_start;
        var_dump('Done checking exchange coinlists in ' . $time_total . ' seconds. A total of '.$deactivated.' coins have been deactivated. A total of '.$activated.' have been activated.');
            
    }

    /**
     * [generateActiveCoinlist description]
     * @param  [type] $exchanges [description]
     * @return [array] coinlists [description]
     */
    protected function generateActiveCoinlist($exchanges) {
        $coinlists = array();
        $activeCoinList = array();
        $activeCoinsOfExchange = array();

        // loop through the exchanges
        foreach($exchanges as $exchange) {
            $classFile = __DIR__ . '/exchanges/'.$exchange->name.'.php';
            if(!file_exists($classFile)) {
                $error = array(
                            'level' => 'Error',
                            'title' => 'Active exchange has no class',
                            'report' => 'Trying to access file: '.$classFile.', but it does not exist. This class needs to exist in order to get the current coinlist!',
                            'active' => 1
                         );
                event(new ReportError($error));
                continue;
            }
            $class = 'App\\Console\\Commands\\exchanges\\'.$exchange->name;

            $activeCoinsOfExchanges[strtolower($exchange->name)] = $class::getActiveCoinlist();
        }

        // foreach($activeCoinsOfExchange as $exchange => $coin) {
        //     if(!in_array($coin, $activeCoinList[$exchange])) $activeCoinList[$exchange][] = $coin;
        // }
        return $activeCoinsOfExchanges;
        // $coinlistsJson = array_values($this->multiple_threads_request($coinlistsUrls));

        // // dd($coinlistsJson);

        // // for($x = 0; $x < count($coinlists); $x++) {
        // //     $json = json_decode($coinlistsJson[$x]);
        // //     $exchange = strtolower($coinlists[$x]);
        // //     $activeCoinLists[$exchange] = $json;
        // // }

        // // if(!is_array($activeCoinLists)) return false;

        return $activeCoinList;

    }

    /**
     * [checkCoinsForDeactivation description]
     * @param  [type] $coins     [description]
     * @param  [type] $exchanges [description]
     * @return [type]            [description]
     */
    protected function checkCoinsForDeactivation($coins, $activeCoinLists) {
        $deactivated = 0;
        // Loop through the coins
        foreach($coins as $coin) {
            // Hard kill if needed
            if(file_get_contents(__DIR__ . '/kill.txt') === 'STOP') dd('hard kill initiated');

            foreach($coin->exchanges as $exchange) {

                if(!$activeCoinLists[strtolower($exchange->name)]) {
                    continue;
                }

                if(!in_array($coin->shortname, $activeCoinLists[strtolower($exchange->name)])) {
                    if($coin->deactivateRelationship($coin->id,$exchange->id)) $deactivated++;
                    
                }
            }
        }
        
        return $deactivated;

    }

    protected function checkCoinsForActivation($coins, $activeCoinLists) {
        $activated = 0;
        // Loop through the coins
        foreach($coins as $coin) {
            // Hard kill if needed
            if(file_get_contents(__DIR__ . '/kill.txt') === 'STOP') dd('hard kill initiated');

            foreach($coin->exchanges as $exchange) {

                if(!$activeCoinLists[strtolower($exchange->name)]) {
                    continue;
                }

                if(in_array($coin->shortname, $activeCoinLists[strtolower($exchange->name)])) {
                    if($coin->activateRelationship($coin->id,$exchange->id)) $activated++;
                }
            }
        }

        return $activated;

    }

    /**
     * [multiple_threads_request description]
     * @param  [type] $nodes [description]
     * @return [type]        [description]
     */
    // protected function multiple_threads_request($nodes){
    //     $mh = curl_multi_init();
    //     $curl_array = array();
    //     foreach($nodes as $i => $url)
    //     {
    //         $curl_array[$i] = curl_init($url);
    //         curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($curl_array[$i], CURLOPT_CONNECTTIMEOUT , 3);
    //         curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 3);
    //         curl_multi_add_handle($mh, $curl_array[$i]);
    //     }
    //     $running = NULL;
    //     do {
    //         curl_multi_exec($mh,$running);
    //     } while($running > 0);

    //     $res = array();
    //     foreach($nodes as $i => $url)
    //     {
    //         $res[$url] = curl_multi_getcontent($curl_array[$i]);
    //     }

    //     foreach($nodes as $i => $url){
    //         curl_multi_remove_handle($mh, $curl_array[$i]);
    //     }
    //     curl_multi_close($mh);
    //     return $res;
    // }


}
