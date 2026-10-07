<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingFeeDiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingFeePromotionRequest;
use App\Models\BookingFeePromotion;
use App\Services\ActivityLogger;
use App\Services\BookingFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Special offers that discount or waive the student booking fee while their
 * window is open. Bookings snapshot the discount they were priced with, so
 * editing an offer never re-prices a hold or a paid lesson.
 */
class BookingFeePromotionController extends Controller
{
    /**
     * List the offers next to the fee the marketplace charges today.
     */
    public function index(BookingFeeService $fees): View
    {
        return view('admin.offers.index', [
            'offers' => BookingFeePromotion::query()
                ->withCount('bookings')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->paginate(20),
            'types' => BookingFeeDiscountType::cases(),
            'bookingFeeMinor' => platform_settings()->int('booking_fee_minor'),
            'feeQuote' => $fees->quote(),
        ]);
    }

    /**
     * Create a special offer.
     */
    public function store(BookingFeePromotionRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $offer = BookingFeePromotion::query()->create($request->offerAttributes());

        $activity->describe('Created booking fee offer "'.$offer->name.'"');

        return redirect()
            ->route('admin.offers.edit', $offer)
            ->with('status', 'offer-created');
    }

    public function edit(BookingFeePromotion $offer): View
    {
        return view('admin.offers.edit', [
            'offer' => $offer,
            'types' => BookingFeeDiscountType::cases(),
        ]);
    }

    /**
     * Update an offer. Only bookings made while it is running are affected.
     */
    public function update(BookingFeePromotionRequest $request, BookingFeePromotion $offer, ActivityLogger $activity): RedirectResponse
    {
        $offer->update($request->offerAttributes());

        $activity->describe('Updated booking fee offer "'.$offer->name.'"');

        return redirect()
            ->route('admin.offers.edit', $offer)
            ->with('status', 'offer-updated');
    }

    /**
     * Remove an offer. Bookings keep the discount they were priced with; only
     * the link to the offer is dropped.
     */
    public function destroy(BookingFeePromotion $offer, ActivityLogger $activity): RedirectResponse
    {
        $name = $offer->name;

        $offer->delete();

        $activity->describe('Deleted booking fee offer "'.$name.'"');

        return redirect()
            ->route('admin.offers.index')
            ->with('status', 'offer-deleted');
    }
}
