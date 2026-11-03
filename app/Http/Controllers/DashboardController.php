<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Genel bakış: yönetilen siteler ve portföy özeti */
    public function index(Request $request)
    {
        $user = $request->user();
        $siteIds = $user->accessibleApartments()->pluck('id');

        return view('dashboard', [
            'propertyCount' => Property::count(),
            'userCount' => User::visibleTo($user)->count(),
            'openTickets' => Ticket::whereIn('apartment_id', $siteIds)->whereIn('status', ['open', 'in_progress'])->count(),
            'recentUsers' => User::visibleTo($user)->latest()->limit(5)->get(),
        ]);
    }

    /** Sitenin genel durumu (eski "Genel Durum" sekmesi) */
    public function site(Request $request, Apartment $apartment, ReportService $reports)
    {
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        return view('site.dashboard', [
            'apartment' => $apartment,
            'summary' => $reports->summary($apartment, $from, $to),
            'performance' => $reports->collectionPerformance($apartment, $from, $to),
            'monthly' => $reports->monthly($apartment),
            'overdue' => $reports->overdueByUnit($apartment)->take(5),
            'recent' => $apartment->cashTransactions()->with('cashbox', 'unit.block')->latest('id')->limit(8)->get(),
            'counts' => [
                'units' => $apartment->units()->count(),
                'members' => $apartment->members()->count(),
                'linked' => $apartment->members()->whereNotNull('user_id')->count(),
                'tickets' => $apartment->tickets()->whereIn('status', ['open', 'in_progress'])->count(),
            ],
            'from' => $from,
            'to' => $to,
        ]);
    }
}
