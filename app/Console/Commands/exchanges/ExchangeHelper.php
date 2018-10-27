<?php

	namespace App\Console\Commands\exchanges;

	class ExchangeHelper {

		public static function calculateTimeDiff($ts) {
			
			// check if ts is in ms
			if(strlen((string)$ts) > 10) $ts = intval($ts / 1000);

			$seconds_diff = time() - $ts;                            
			$time = ($seconds_diff/3600);

			if($time < 0) $time = abs($time);

			return $time;

		}

	}