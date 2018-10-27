<?php
namespace App\Console\Commands\exchanges;
use App\Console\Commands\exchanges\ExchangeInterface;

class CryptoCompare extends ExchangeHelper implements ExchangeInterface
{
	protected $version = '1.1';

	public static function getCoinJsonFile($coin) {
		return 'https://min-api.cryptocompare.com/data/pricemultifull?fsyms='.$coin.'&tsyms=USD';
	}

	public static function getPriceFromFile($file,$rate,$coin) {

		if(isset($file->Response) && $file->Response == 'Error') return false;

		// calculate time difference
		$timeDiff = self::calculateTimeDiff($file->RAW->$coin->USD->LASTUPDATE);
		if($timeDiff >= 30) return false;

		$price = isset($file->RAW->$coin->USD->PRICE) ? $file->RAW->$coin->USD->PRICE : false;
		return $price;

	}

	public static function getMarketCapFromFile($file,$coin)
	{

		if(isset($file->Response) && $file->Response == 'Error') return false;

		// calculate time difference
		$timeDiff = self::calculateTimeDiff($file->RAW->$coin->USD->LASTUPDATE);
		if($timeDiff >= 30) return false;

		$cap = isset($file->RAW->$coin->USD->MKTCAP) ? $file->RAW->$coin->USD->MKTCAP : false;
		return $cap;
	}

	/**
	 * CryptoCompares volumes are based per market, not globally
	 */
	public static function getVolumeFromFile($file,$coin)
	{
		return false;
		// $cap = isset($file->RAW->$coin->USD->VOLUME24HOURTO) ? $file->RAW->$coin->USD->VOLUME24HOURTO : false;
		// return $cap;
	}


	public static function getActiveCoinlist()
	{
		$json = json_decode(file_get_contents('https://www.cryptocompare.com/api/data/coinlist/'),true);
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

		foreach($json['Data'] as $short => $coin) {
			$activeCoins[] = $short;
		}

		return $activeCoins;
	}
}