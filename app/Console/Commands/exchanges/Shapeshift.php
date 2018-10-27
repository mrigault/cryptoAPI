<?php
namespace App\Console\Commands\exchanges;
use App\Console\Commands\exchanges\ExchangeInterface;

class Shapeshift implements ExchangeInterface
{
	protected $version = '1.0';
	protected $sourceInBTC = true;

	public static function getCoinJsonFile($coin) {
		if(strtolower($coin) == 'btc') return false;
		return 'https://shapeshift.io/marketinfo/'.$coin.'_btc';
	}

	public static function getPriceFromFile($file,$rate,$coin) {
		$price = isset($file->rate) ? $file->rate : false;

		$price = floatval($rate * $price);

		return $price;

	}


	public static function getMarketCapFromFile($file,$coin)
	{
		throw new \Exception('Method not implemented');
	}

	public static function getVolumeFromFile($file,$coin)
	{
		throw new \Exception('Method not implemented');
	}

}