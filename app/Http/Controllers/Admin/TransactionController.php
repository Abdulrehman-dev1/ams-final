<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('perPage', 10);
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $query = Transaction::query()
            ->latest('date')
            ->latest('check_in');

        if ($request->filled('person_code')) {
            $query->where('person_code', 'like', '%'.$request->input('person_code').'%');
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->input('name').'%');
        }

        $selectedDate = $request->input('date');
        if (!$selectedDate) {
            $selectedDate = Carbon::today()->format('Y-m-d');
        }

        $query->whereDate('date', $selectedDate);

        $transactions = $query->paginate($perPage)->appends($request->query());

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'filters' => [
                'person_code' => $request->input('person_code', ''),
                'name' => $request->input('name', ''),
                'date' => $selectedDate,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function build(Request $request)
    {
        try {
            Artisan::call('transactions:build', [
                '--no-interaction' => true,
            ]);

            return redirect()->route('admin.transactions.index')->with('transaction_flash', [
                'type' => 'success',
                'title' => 'Transaction sync started',
                'message' => 'php artisan transaction:build finished running. Latest API data should appear shortly.',
                'output' => trim(Artisan::output()),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.transactions.index')->with('transaction_flash', [
                'type' => 'danger',
                'title' => 'Transaction sync failed',
                'message' => $e->getMessage(),
            ]);
        }
    }
}

