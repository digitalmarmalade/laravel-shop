<?php

namespace Amsgames\LaravelShop\Traits;

/**
 * This file is part of LaravelShop,
 * A shop solution for Laravel.
 *
 * @author Alejandro Mostajo
 * @copyright Amsgames, LLC
 * @license MIT
 * @package Amsgames\LaravelShop
 */

use Auth;
use Shop;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

trait ShopVoucherTrait
{
    use ShopItemTrait;

    /**
     * Returns price formatted for display.
     *
     * @return string
     */
    public function getDisplayPriceAttribute()
    {
        return Shop::format($this->getPriceAttribute());
    }
    
    public function getIsVoucherAttribute()
    {
        return true;
    }
    
    public function getPrice($cart)
    {
        $cartTotalPrice = $cart->total_price + $cart->total_tax;
        if ($cartTotalPrice >= $this->attributes['minimum_price']) {
            
            if ($this->attributes['percentage_adjustment'] > 0) {
                return ($cartTotalPrice / 100) * $this->attributes['percentage_adjustment'] * -1;
            }
            
            if ($this->fixed_price_adjustment > 0) {
                //if the fixed price adjustment is more than the total cart price, then return -totalCartPrice, else return -fixedPriceAdjustment
                return ($cartTotalPrice > $this->attributes['fixed_price_adjustment'])
                    ? $this->attributes['fixed_price_adjustment'] * -1
                    : $cartTotalPrice * -1;
            }
            
        }
        
        //if nothing matched above then this does not apply so return 0
        return 0;
    }
    
}