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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Amsgames\LaravelShop\Models\ShopCartModel;
use InvalidArgumentException;

trait ShopCartTrait
{
    /**
     * Property used to stored calculations.
     * @var array
     */
    private $cartCalculations = null;

    /**
     * Boot the user model
     * Attach event listener to remove the relationship records when trying to delete
     * Will NOT delete any records if the user model uses soft deletes.
     *
     * @return void|bool
     */
    public static function boot()
    {
        parent::boot();

        static::deleting(function($user) {
            if (!method_exists(config('shop.user'), 'bootSoftDeletingTrait')) {
                $user->items()->sync([]);
            }

            return true;
        });
    }

    /**
     * One-to-One relations with the user model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function user()
    {
        return $this->belongsTo(config('shop.user'), 'user_id');
    }

    public function sessionUser()
    {
        return $this->where('session_id', Session::get('laravel-shop_cart_session_id'));
    }

    /**
     * One-to-Many relations with Item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function items()
    {
        return $this->hasMany(Config::get('shop.item'), 'cart_id');
    }

    /**
     * Returns the total number of items in the basket
     * @return int
     */
    public function totalItems()
    {
        $items = $this->items()->get();
        $total = 0;
        foreach ($items as $item) {
            $total += $item->quantity;
        }
        return $total;
    }

    /**
     * Adds item to cart.
     *
     * @param mixed $item     Item to add, can be an Store Item, a Model with ShopItemTrait or an array.
     * @param int   $quantity Item quantity in cart.
     */
    public function add($item, $quantity = 1, $quantityReset = false)
    {
        if (!is_array($item) && !$item->isShoppable)
            return;

        // Get item
        $cartItem = $this->getItem(is_array($item) ? $item['sku'] : $item->sku);


        // Add new or sum quantity
        if (empty($cartItem)) {

            $reflection = null;
            if (is_object($item)) {
                $reflection = new \ReflectionClass($item);
            }

            $itemClass = config('shop.item');
            $cartItem = new $itemClass;

            $cartClass = config('shop.cart');

            if (Auth::guard(config('shop.user_auth_provider'))->guest()) {
                $cartItem->session_id = $cartClass::getSessionId();
            } else {
                $cartItem->user_id = $this->user->shopId;
            }

            $cartItem->cart_id = $this->attributes['id'];
            $cartItem->sku = is_array($item) ? $item['sku'] : $item->sku;
            $cartItem->price = is_array($item) ? $item['price'] : $item->price;
            $cartItem->tax = is_array($item) ? (array_key_exists('tax', $item) ? $item['tax'] : 0
                    ) : (isset($item->tax) && !empty($item->tax) ? $item->tax : 0
                    );
            $cartItem->shipping = is_array($item) ? (array_key_exists('shipping', $item) ? $item['shipping'] : 0
                    ) : (isset($item->shipping) && !empty($item->shipping) ? $item->shipping : 0
                    );
            $cartItem->currency = config('shop.currency');
            $cartItem->quantity = $quantity;
            $cartItem->class = is_array($item) ? null : $reflection->getName();
            $cartItem->reference_id = is_array($item) ? null : $item->shopId;
        } else {
            $cartItem->quantity = $quantityReset ? $quantity : $cartItem->quantity + $quantity;
        }
        $cartItem->save();
        $this->processVouchers();
        $this->resetCalculations();
        return $this;
    }

    public function addVoucher($item)
    {
        $currentVoucherItems = $this->getVoucherItems();
        if (count($currentVoucherItems) > 0) {
            foreach ($currentVoucherItems as $currentVoucherItem) {
                $this->remove($currentVoucherItem);
            }
        }
        if (strtotime($item->expiry) > time()) { //it's not expired
            return $this->add($item, 1);
        } else {
            return $this;
        }
    }

    public function getVoucherItems()
    {
        $items = $this->items()->get();
        $vouchers = [];

        foreach ($items as $item) {
            if ($item->object->isVoucher) {
                $vouchers[] = $item;
            }
        }
        return $vouchers;
    }

    /**
     * Removes an item from the cart or decreases its quantity.
     * Returns flag indicating if removal was successful.
     *
     * @param mixed $item     Item to remove, can be an Store Item, a Model with ShopItemTrait or an array.
     * @param int   $quantity Item quantity to decrease. 0 if wanted item to be removed completly.
     *
     * @return bool
     */
    public function remove($item, $quantity = 0)
    {
        // Get item
        $cartItem = $this->getItem(is_array($item) ? $item['sku'] : $item->sku);
        // Remove or decrease quantity
        if (!empty($cartItem)) {
            if (!empty($quantity)) {
                $cartItem->quantity -= $quantity;
                $cartItem->save();
                if ($cartItem->quantity > 0)
                    return true;
            }
            $cartItem->delete();
        }
        $this->processVouchers();
        $this->resetCalculations();
        return $this;
    }

    /**
     * Checks if the user has a role by its name.
     *
     * @param string|array $name       Role name or array of role names.
     * @param bool         $requireAll All roles in the array are required.
     *
     * @return bool
     */
    public function hasItem($sku, $requireAll = false)
    {
        if (is_array($sku)) {
            foreach ($sku as $skuSingle) {
                $hasItem = $this->hasItem($skuSingle);

                if ($hasItem && !$requireAll) {
                    return true;
                } elseif (!$hasItem && $requireAll) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the roles were found
            // If we've made it this far and $requireAll is TRUE, then ALL of the roles were found.
            // Return the value of $requireAll;
            return $requireAll;
        } else {
            foreach ($this->items as $item) {
                if ($item->sku == $sku) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Scope class by a given user ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query  Query.
     * @param mixed                                 $userId User ID.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope class by a given session ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query  Query.
     * @param mixed                                 $sessionId User ID.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to current user cart.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query  Query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereCurrent($query)
    {
        if (Auth::guard(config('shop.user_auth_provider'))->guest()) {
            $cartClass = config('shop.cart');
            return $query->whereSession($cartClass::getSessionId());
        } else {
            return $query->whereUser(Auth::guard(config('shop.user_auth_provider'))->user()->shopId);
        }
    }

    public function scopeCurrentSession($query)
    {
        $cartClass = config('shop.cart');
        $sessionCart = $query->whereSession($cartClass::getSessionId())->first();
        if (empty($sessionCart)) {
            $cartClass = config('shop.cart');
            $cart = new $cartClass;
            $cart->session_id = $cartClass::getSessionId();
            $cart->save();
            return $cart;
        } else {
            return $sessionCart;
        }
    }

    /**
     * Scope to current user cart and returns class model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query  Query.
     *
     * @return this
     */
    public function scopeCurrent($query)
    {
        $cart = $query->whereCurrent()->first();
        if (empty($cart)) {
            $cartClass = config('shop.cart');
            $cart = new $cartClass;
            if (Auth::guard(config('shop.user_auth_provider'))->guest()) {
                $cart->session_id = $cartClass::getSessionId();
            } else {
                $cart->user_id = Auth::guard(config('shop.user_auth_provider'))->user()->shopId;
            }
            $cart->save();
        }
        return $cart;
    }

    /**
     * Scope to current user cart and returns class model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query  Query.
     *
     * @return this
     */
    public function scopeFindByUser($query, $userId)
    {
        if (empty($userId))
            return;
        $cart = $query->whereUser($userId)->first();
        if (empty($cart)) {
            $cartClass = config('shop.cart');
            $cart = new $cartClass;
            $cart->user_id = $userId;
            $cart->save();
        }
        return $cart;
    }

    /**
     * Transforms cart into an order.
     * Returns created order.
     *
     * @param string $statusCode Order status to create order with.
     *
     * @return Order
     */
    public function placeOrder($statusCode = null)
    {
        if (empty($statusCode))
            $statusCode = Config::get('shop.order_status_placement');
        // Create order
        $orderClass = config('shop.order');
        $order = new $orderClass;

        $order->user_id = $this->user_id;
        $order->statusCode = $statusCode;
        $order->price = $this->totalPrice;
        $order->shipping = $this->totalShipping;
        $order->tax = $this->totalTax;
        $order->total_price = $this->total;
        $order->save();

        // Map cart items into order
        for ($i = count($this->items) - 1; $i >= 0; --$i) {
            // Attach to order
            $this->items[$i]->order_id = $order->id;
            // Remove from cart
            $this->items[$i]->cart_id = null;
            // Update
            $this->items[$i]->save();
        }
        $this->resetCalculations();
        return $order;
    }

    /**
     * Set a fixed shipping amount on the cart which overrides individual item shipping amounts
     * @param float $shipping
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;
        $this->save();
        $this->resetCalculations();
        return $this;
    }

    /**
     * Whipes put cart
     */
    public function clear()
    {
        DB::table(Config::get('shop.item_table'))
                ->where('cart_id', $this->attributes['id'])
                ->delete();
        $this->resetCalculations();
        return $this;
    }

    private function processVouchers()
    {
        $voucherItems = $this->getVoucherItems();

        foreach ($voucherItems as $voucherItem) {
            $voucher = $voucherItem->object;

            $newVoucherPrice = $voucher->getPrice($this);

            if ($voucherItem->price !== $newVoucherPrice) {
                $voucherItem->price = $newVoucherPrice;
                $voucherItem->save();
            }
        }
    }

    /**
     * Retrieves item from cart;
     *
     * @param string $sku SKU of item.
     *
     * @return mixed
     */
    private function getItem($sku, $cartId = null)
    {
        $className = Config::get('shop.item');
        $item = new $className();
        return $item->where('sku', $sku)
                        ->where('cart_id', ($cartId === null ? $this->attributes['id'] : $cartId))
                        ->first();
    }

    /**
     * Gets the session id that is used by the cart table to find the current session cart
     * If the session id is not set it creates one
     * @return type
     */
    public function getSessionId()
    {
        if (Session::get('laravel-shop_cart_session_id', false) === false) {
            Session::put('laravel-shop_cart_session_id', str_random(64) . '_' . time());
        }
        return Session::get('laravel-shop_cart_session_id');
    }

    /**
     * Merges current cart with another cart
     * @param ShopCartModel $cart
     */
    public function merge(ShopCartModel $cart)
    {
        $mergeCartItems = $cart->items()->get();
        foreach ($mergeCartItems as $mergeCartItem) {
            $this->add($this->getItem($mergeCartItem->sku, $cart->id), $mergeCartItem->quantity);
        }
    }
}