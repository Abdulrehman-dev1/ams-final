<?php
// app/Http/Controllers/PersonController.php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PersonController extends Controller
{
    /**
     * GET /api/persons
     * Query params:
     *  - q (name/email/phone like)
     *  - group_id
     *  - per_page (default 15)
     */
    public function index(Request $req)
    {
        $perPage  = (int)($req->integer('per_page') ?: 15);
        $q        = trim((string)$req->query('q', ''));
        $groupId  = $req->query('group_id');

        $persons = Person::query()
            ->when($q, function ($qBuilder) use ($q) {
                $qBuilder->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('person_code', 'like', "%{$q}%");
                });
            })
            ->when($groupId, fn($b) => $b->where('group_id', (string)$groupId))
            ->orderBy('first_name')
            ->paginate($perPage);

        return response()->json($persons);
    }

    /**
     * GET /api/persons/{person}
     */
    public function show(Person $person)
    {
        return response()->json($person);
    }

    /**
     * POST /api/persons/sync
     * Body (optional):
     *  - page_size (1..100, default 100)
     *  - max_pages (limit loop; default null = run until empty)
     *  - filter: { name, email, phone }  // forwarded to Hik
     */
    public function sync(Request $req)
    {
        // Validate optional inputs
        $v = Validator::make($req->all(), [
            'page_size' => 'nullable|integer|min:1|max:100',
            'max_pages' => 'nullable|integer|min:1|max:10000',
            'filter'    => 'nullable|array',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $pageSize  = (int)($req->input('page_size', 100));
        $maxPages  = $req->input('max_pages'); // null = keep going
        $filter    = $req->input('filter', (object)[]);

        // Config: set these in config/hik.php or .env
        $base  = config('hik.base_url');   // e.g. https://ius.hikcentralconnect.com
        $token = app('hik.token');         // resolve your cached token service

        if (!$base || !$token) {
            return response()->json(['message' => 'Hik base URL or Token missing'], 500);
        }

        $page   = 1;
        $total  = 0;

        do {
            $payload = [
                'pageIndex' => $page,
                'pageSize'  => $pageSize,
                'filter'    => (object)$filter, // keep object if empty
            ];

            $res = Http::timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Token'        => $token,
                ])
                ->post(rtrim($base, '/').'/api/hccgw/person/v1/persons/list', $payload);

            if (!$res->ok()) {
                Log::warning("Hik persons sync HTTP {$res->status()}", ['body' => $res->body()]);
                return response()->json([
                    'message' => 'Hik API error',
                    'status'  => $res->status(),
                    'body'    => $res->json(),
                ], 502);
            }

            $json = $res->json();
            $list = data_get($json, 'data.personList', []); // adjust if your portal returns different path

            foreach ($list as $item) {
                $pi = $item['personInfo'] ?? [];

                $startMs = $pi['startDate'] ?? null;
                $endMs   = $pi['endDate']   ?? null;

                $startAt = $startMs ? Carbon::createFromTimestampMs($startMs) : null;
                $endAt   = $endMs   ? Carbon::createFromTimestampMs($endMs)   : null;

                Person::updateOrCreate(
                    ['person_code' => $pi['personCode'] ?? null], // stable unique key
                    [
                        'person_id'    => $pi['personId']   ?? null,
                        'group_id'     => (string)($pi['groupId'] ?? null),
                        'first_name'   => $pi['firstName']  ?? null,
                        'last_name'    => $pi['lastName']   ?? null,
                        'gender'       => $pi['gender']     ?? null,
                        'phone'        => $pi['phone']      ?? null,
                        'email'        => $pi['email']      ?? null,
                        'description'  => $pi['description']?? null,
                        'head_pic_url' => $pi['headPicUrl'] ?? null,
                        'start_ms'     => $startMs,
                        'end_ms'       => $endMs,
                        'start_at'     => $startAt,
                        'end_at'       => $endAt,
                    ]
                );
                $total++;
            }

            $count = count($list);
            $page++;

            if ($maxPages && $page > (int)$maxPages) {
                break;
            }

        } while (!empty($list)); // stop when current page returned 0 rows

        return response()->json(['synced' => $total]);
    }
}
