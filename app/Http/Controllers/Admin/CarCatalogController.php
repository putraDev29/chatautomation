<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;

class CarCatalogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');
        $status = $request->query('status');
        $brand = $request->query('brand');

        $query = Car::with('media');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('model', 'LIKE', "%{$search}%")
                  ->orWhere('brand', 'LIKE', "%{$search}%")
                  ->orWhere('stock_code', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('stock_status', $status);
        }

        if (!empty($brand)) {
            $query->where('brand', $brand);
        }

        $cars = $query->orderBy('stock_status', 'asc')
                      ->orderBy('year', 'desc')
                      ->paginate(12)
                      ->withQueryString();

        $brands = Car::select('brand')->distinct()->pluck('brand');

        return view('admin.cars.index', compact('cars', 'brands', 'search', 'status', 'brand'));
    }

    public function updateStatus(Request $request, Car $car)
    {
        $request->validate([
            'stock_status' => 'required|in:TERSEDIA,BOOKING,TERJUAL,TIDAK AKTIF',
            'is_published' => 'nullable|boolean',
        ]);

        $car->update([
            'stock_status' => $request->input('stock_status'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        return back()->with('success', "Status unit {$car->full_name} berhasil diperbarui.");
    }
}
