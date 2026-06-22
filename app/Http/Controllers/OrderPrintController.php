<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class OrderPrintController extends Controller
{
    /**
     * Print standard customer invoice.
     */
    public function printInvoice(Order $order): View
    {
        // Guard access: owner or admin only
        if ($order->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        $order->load(['user', 'payment', 'items.product']);

        return view('print.invoice', compact('order'));
    }

    /**
     * Print courier shipping label.
     */
    public function printShippingLabel(Order $order): View
    {
        // Guard access: admin only
        if (! Auth::user()->isAdmin()) {
            abort(403);
        }

        $order->load(['user', 'items.product']);

        return view('print.shipping-label', compact('order'));
    }
}
