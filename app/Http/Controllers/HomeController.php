<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    
	/**
	 * Default '/' index. No request, so return error
	 * @return JSON error response
	 */
	public function index() {


		return response()->json([
			'status' => 'error',
			'error' => [
				'message' => 'Please provide a request.',
				'status_code' => 400
			]
			
		], 400);

	}

}