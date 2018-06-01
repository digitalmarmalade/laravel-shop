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
use Shop;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

trait ShopCalculationsTrait
{
    /**
     * Property used to stored calculations.
     * @var array
     */
    private $shopCalculations = null;

    /**
     * Returns total amount of items in cart.
     *
     * @return int
     */
    public function getCountAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return round($this->shopCalculations->item_count, 2);
    }

    /**
     * Returns total price of all the items in cart.
     *
     * @return float
     */
    public function getTotalPriceAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return round($this->shopCalculations->total_price, 2);
    }

    /**
     * Returns total tax of all the items in cart.
     *
     * @return float
     */
    public function getTotalTaxAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return round($this->shopCalculations->total_tax, 2);
    }

    /**
     * Returns total tax of all the items in cart.
     *
     * @return float
     */
    public function getTotalShippingAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return round($this->shopCalculations->total_shipping, 2);
    }

    /**
     * Returns total discount amount based on all coupons applied.
     *
     * @return float
     */
    public function getTotalDiscountAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return round($this->shopCalculations->total_discount, 2);
    }

    /**
     * Returns total amount to be charged base on total price, tax and discount.
     *
     * @return float
     */
    public function getTotalAttribute()
    {
        if (empty($this->shopCalculations))
            $this->runCalculations();
        return $this->total_price + $this->total_tax + $this->total_shipping + $this->total_discount;
    }

    /**
     * Returns formatted total price of all the items in cart.
     *
     * @return string
     */
    public function getDisplayTotalPriceAttribute()
    {
        return Shop::format($this->total_price, 2);
    }

    /**
     * Returns formatted total tax of all the items in cart.
     *
     * @return string
     */
    public function getDisplayTotalTaxAttribute()
    {
        return Shop::format($this->total_tax, 2);
    }

    /**
     * Returns formatted total tax of all the items in cart.
     *
     * @return string
     */
    public function getDisplayTotalShippingAttribute()
    {
        return Shop::format($this->total_shipping, 2);
    }

    /**
     * Returns formatted total discount amount based on all coupons applied.
     *
     * @return string
     */
    public function getDisplayTotalDiscountAttribute()
    {
        return Shop::format($this->total_discount, 2);
    }

    /**
     * Returns formatted total amount to be charged base on total price, tax and discount.
     *
     * @return string
     */
    public function getDisplayTotalAttribute()
    {
        return Shop::format($this->total, 2);
    }

    /**
     * Returns cache key used to store calculations.
     *
     * @return string.
     */
    public function getCalculationsCacheKeyAttribute()
    {
        return 'shop_' . $this->table . '_' . $this->attributes['id'] . '_calculations';
    }

    /**
     * Runs calculations.
     */
    private function runCalculations()
    {
        if (!empty($this->shopCalculations))
            return $this->shopCalculations;
        $cacheKey = $this->calculationsCacheKey;
        if (Config::get('shop.cache_calculations') && Cache::has($cacheKey)
        ) {
            $this->shopCalculations = Cache::get($cacheKey);
            return $this->shopCalculations;
        }
        $this->shopCalculations = (object) [
                    'item_count' => 0.0,
                    'total_price' => 0.0,
                    'total_tax' => 0.0,
                    'total_shipping' => 0.0,
                    'total_discount' => 0.0
        ];

        $items = $this->items()->get();
        foreach ($items as $item) {
            if ($item->object->isVoucher) {
                $this->shopCalculations->total_discount += $item->price;
            } else {
                $this->shopCalculations->item_count += $item->quantity;
                $this->shopCalculations->total_price += $item->price * $item->quantity;
                $this->shopCalculations->total_shipping += $item->shipping;
            }
        }
        $this->shopCalculations->total_tax += round(($item->price * $item->quantity) * config('shop.tax'), 2);

        //We have a fixed shipping amount, override the per item
        if (floatval($this->shipping) !== floatval(0)) {
            $this->shopCalculations->total_shipping = $this->shipping;
        }

        if (Config::get('shop.cache_calculations')) {
            Cache::put(
                    $cacheKey, $this->shopCalculations, Config::get('shop.cache_calculations_minutes')
            );
        }
        return $this->shopCalculations;
    }

    /**
     * Resets cart calculations.
     */
    private function resetCalculations()
    {
        $this->shopCalculations = null;
        if (Config::get('shop.cache_calculations')) {
            Cache::forget($this->calculationsCacheKey);
        }
    }
}