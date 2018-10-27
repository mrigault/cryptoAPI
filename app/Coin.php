<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Coin extends Model
{

    /**
     * Fillable fields in the db
     * @var array
     */
    protected $fillable = ['fullname','shortname','updated_at','active'];

	/**
     * Get associated exchanges
     * Returns relationship by coin_exchange table
     * @return collection exchanges
     */
    public function exchanges() {
      return $this->belongsToMany(Exchange::class, 'coin_exchange', 'coin_id', 'exchange_id');
    }

    /**
     * Checks if the coins has an active relationship with any exchange
     * @param  int coin_id
     * @return boolean
     */
    public function hasAnyActiveRelationship($coin_id) {
        
        $active = DB::table('coin_exchange')
                            ->where([
                                ['coin_id', '=', $coin_id],
                                ['active', '=', 1]
                            ])
                            ->get();

        if(!$active->isEmpty()) {
            return true;
        }

        return false;

    }

    /**
     * Activate relationship between coin and exchange
     * This will update the pivot table coin_exchange
     * @param  int coin_id
     * @param  int exchange_id
     * @return boolean
     */
    public function activateRelationship($coin_id, $exchange_id) {

        if(!$this->hasActiveRelationshipWith($coin_id,$exchange_id)) {
            DB::table('coin_exchange')
                           ->where([
                            ['coin_id', '=', $coin_id],
                            ['exchange_id', '=', $exchange_id]
                            ])
                           ->update(['active' => 1]);

            return true;
        }

        return false;
    }

    /**
     * Dectivate relationship between coin and exchange
     * This will update the pivot table coin_exchange
     * @param  int coin_id
     * @param  int exchange_id
     * @return boolean
     */
    public function deactivateRelationship($coin_id, $exchange_id) {

        if($this->hasActiveRelationshipWith($coin_id,$exchange_id)) {
            DB::table('coin_exchange')
                           ->where([
                               ['coin_id', '=', $coin_id],
                               ['exchange_id', '=', $exchange_id]
                            ])
                           ->update(['active' => 0]);

            // deactivate coin if no more relations exist
            if(!$this->hasAnyActiveRelationship($coin_id)) {
                DB::table('coins')
                               ->where([
                                   ['id', '=', $coin_id],
                                ])
                               ->update(['active' => 0]);
            }

            return true;

        }

        return false;
    }

    /**
     * Checks if the coins has an active relationship with exchange
     * @param  int coin_id
     * @param  int exchange_id
     * @return boolean
     */
    public function hasActiveRelationshipWith($coin_id,$exchange_id) {
        
        $active = DB::table('coin_exchange')
                            ->where([
                                ['coin_id', '=', $coin_id],
                                ['exchange_id', '=', $exchange_id],
                                ['active', '=', 1]
                            ])
                            ->get();

        if(!$active->isEmpty()) return true;

        return false;

    }

    /*
     * Activate the coin
     */
    public static function activateCoin($id) {

        return static::where('id',$id)->update(['active' => 1]);

    }

    /*
     * Deactivate the coin
     */
    public static function deactivateCoin($id) {

        return static::where('id',$id)->update(['active' => 0]);

    }

}
