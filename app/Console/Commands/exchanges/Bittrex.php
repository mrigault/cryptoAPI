<?php
namespace App\Console\Commands\exchanges;

use App\Console\Commands\exchanges\ExchangeInterface;

class Bittrex implements ExchangeInterface
{
	protected $version = '1.0';
	protected $sourceInBTC = true;

	public static function getCoinJsonFile($coin) {
		if(strtolower($coin) == 'btc') return false;
		return 'https://bittrex.com/api/v1.1/public/getmarketsummary?market=btc-'.$coin;
	}

	public static function getPriceFromFile($file,$rate,$coin) {
		
		$price = isset($file->result[0]->Last) ? $file->result[0]->Last : false;
		$price = floatval($rate * $price);

		return $price;

	}

	public static function getMarketCapFromFile($file,$coin)
	{
		return false; // bittrex returnt alleen volume van eigen markten
	}

	public static function getVolumeFromFile($file,$coin)
	{
		return false; // bittrex returnt alleen volume van eigen markten
	}


	public static function getActiveCoinlist()
	{
		$json = json_decode(file_get_contents('https://bittrex.com/api/v1.1/public/getmarkets'));
		$activeCoins = array();
		
		if(!$json || empty($json)) {
			$error = array(
                            'level' => 'Error',
                            'title' => 'Active exchange did not return a coinlist',
                            'report' => 'Trying to access coinlist from: '.$classFile.', however none could be retrieved',
                            'active' => 1
                         );
                event(new ReportError($error));
           return $activeCoins;
		}

		foreach($json->result as $coin) {
			if($coin->IsActive == false) continue;
			$activeCoins[] = $coin->MarketCurrency;
		}
		return $activeCoins;
	}

}