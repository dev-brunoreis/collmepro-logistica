<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel for order updates - all authenticated users can listen
Broadcast::channel('orders', function ($user) {
    return $user !== null;
});

// Channel for specific order updates
Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return $user !== null;
});