<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;

class ReviewController extends Controller
{
    public function __invoke()
    {
        $reviews = Review::with(['customer', 'technician'])->latest()->get();
        $count = max($reviews->count(), 1);
        $completed = Booking::whereIn('status', Booking::PAID_STATUSES)->count();

        return view('reviews.index', [
            'reviews' => $reviews,
            'avg' => number_format((float) $reviews->avg('rating'), 1),
            'five' => $reviews->where('rating', 5)->count(),
            'fivePct' => round($reviews->where('rating', 5)->count() / $count * 100),
            'negative' => $reviews->where('rating', '<=', 3)->count(),
            'responseRate' => $completed ? min(100, round($reviews->count() / $completed * 100)) : 0,
        ]);
    }
}
