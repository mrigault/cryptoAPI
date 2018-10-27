<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
     /*
     * Get associated coins
     * Returns relationship by coin_exchange table
     */
    public function coins() {
       return $this->belongsToMany(Coin::class, 'coin_exchange', 'exchange_id', 'coin_id');
    }

}
