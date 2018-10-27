<?php
namespace App\Console\Commands\exchanges;
use App\Console\Commands\exchanges\ExchangeHelper;
use App\Console\Commands\exchanges\ExchangeInterface;

class CoinCap extends ExchangeHelper implements ExchangeInterface
{
	protected $version = '1.1';
	protected $sourceInBTC = false;

	public static function getActiveCoinlist() {
		$json = json_decode(file_get_contents('http://coincap.io/coins/'));

		if(!$json || empty($json)) {
			$error = array(
                            'level' => 'Error',
                            'title' => 'Active exchange did not return a coinlist',
                            'report' => 'Trying to access coinlist from: '.$classFile.', however none could be retrieved',
                            'active' => 1
                         );
                event(new ReportError($error));
           return false;
		}
		return $json;
	}

	public static function getCoinJsonFile($coin) {
		if(strtolower($coin) == 'btc') return false;
		return 'http://www.coincap.io/history/'.strtoupper($coin);
	}

	public static function getPriceFromFile($file,$rate,$coin) {

		if(!isset($file->price)) return false;

		$prices = $file->price;
		$price = end($prices);

		// calculate time difference
		$timeDiff = self::calculateTimeDiff($price[0]);
		if($timeDiff >= 30) return false;

		$price = $price[1];
		return $price;
	}

	public static function getMarketCapFromFile($file,$coin)
	{
		if(!isset($file->market_cap)) return false;

		$caps= $file->market_cap;
		$cap = end($caps);

		// calculate time difference
		$timeDiff = self::calculateTimeDiff($cap[0]);
		if($timeDiff >= 30) return false;

		$cap = $cap[1];
		return $cap;
	}

	public static function getVolumeFromFile($file,$coin)
	{

		if(!isset($file->volume)) return false;

		$volumes = $file->volume;
		$volume = end($volumes);

		// calculate time difference
		$timeDiff = self::calculateTimeDiff($volume[0]);
		if($timeDiff >= 30) return false;

		$volume = $volume[1];

		return $volume;
	}

}