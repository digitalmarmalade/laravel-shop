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
    
    public function getPrice($cart)
    {
        $cartTotalPrice = $this->getCartTotalPriceExcludingVouchers($cart);
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
    
    private function getCartTotalPriceExcludingVouchers($cart)
    {
        $items = $cart->items()->get();
        $totalPrice = 0;
        
        foreach ($items as $item) {
            $product = $item->object;
            
            //It's not voucher
            if (!in_array(ShopVoucherTrait::class, class_uses($product))) {
                $totalPrice += ($item->price * $item->quantity);
            }
        }
        
        return $totalPrice;
    }
    
    

}