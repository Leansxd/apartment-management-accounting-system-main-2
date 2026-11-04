<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $range = array_key_exists((string) $request->query('range'), DashboardService::RANGES) ? $request->query('range') : 'month';
        $tab = in_array($request->query('tab'), ['admins', 'residents', 'super'], true) ? $request->query('tab') : null;
        $sort = $request->query('sort') === 'name' ? 'name' : 'latest';
        $search = trim((string) $request->query('q'));

        return view('dashboard', $this->dashboard->overview($user, $range) + [
            'users' => $this->dashboard->users($user, $tab, $search ?: null, $sort)->limit(8)->get(),
            'tab' => $tab, 'sort' => $sort, 'search' => $search,
        ]);
    }

    public function site(Request $request, Apartment $apartment)
    {
        $range = array_key_exists((string) $request->query('range'), DashboardService::RANGES) ? $request->query('range') : 'month';

        return view('site.dashboard', $this->dashboard->site($apartment, $range) + ['apartment' => $apartment]);
    }
}
