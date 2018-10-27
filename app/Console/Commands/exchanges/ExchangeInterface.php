<?php
	namespace App\Console\Commands\exchanges;


	interface ExchangeInterface {

		public static function getActiveCoinlist();
		public static function getCoinJsonFile($coin);
		public static function getPriceFromFile($file,$rate,$coin);
		public static function getMarketCapFromFile($file,$coin);
		public static function getVolumeFromFile($file,$coin);

	}