<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Exports\LabelsExport;
use App\Services\LabelPdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LabelController extends Controller
{
    /**
     * Display a listing of labels with filters and search
     */
    public function index(Request $request): Response
    {
        $query = Label::query()->with('orderSummary');

        // Apply search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply date range filter
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Apply status filter
        if ($request->filled('status')) {
            if ($request->status === 'posted') {
                $query->posted();
            } elseif ($request->status === 'not_posted') {
                $query->notPosted();
            }
        }

        // Apply "generated today" quick filter
        if ($request->boolean('today')) {
            $query->generatedToday();
        }

        // Order by issue_date descending by default
        $query->orderBy('issue_date', 'desc');

        // Paginate results
        $perPage = $request->input('per_page', 50);
        $labels = $query->paginate($perPage)->withQueryString();

        // Add computed is_posted attribute to each label
        $labels->getCollection()->transform(function ($label) {
            $label->is_posted = $label->is_posted;
            $label->status_text = $label->status_text;
            return $label;
        });

        return Inertia::render('Labels/Index', [
            'labels' => $labels,
            'filters' => [
                'search' => $request->search,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status,
                'today' => $request->boolean('today'),
            ],
        ]);
    }

    /**
     * Export labels to XLS or PDF
     */
    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $request->validate([
            'format' => 'required|in:xls,pdf',
        ]);

        $query = Label::query()->with('orderSummary');

        // Apply same filters as index
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('status')) {
            if ($request->status === 'posted') {
                $query->posted();
            } elseif ($request->status === 'not_posted') {
                $query->notPosted();
            }
        }

        if ($request->boolean('today')) {
            $query->generatedToday();
        }

        $query->orderBy('issue_date', 'desc');

        $format = $request->format;
        $timestamp = now()->format('Y-m-d_His');
        $filename = "etiquetas_{$timestamp}";

        if ($format === 'xls') {
            return Excel::download(
                new LabelsExport($query),
                "{$filename}.xlsx"
            );
        } else {
            // PDF export
            $labels = $query->get();
            
            // Add computed attributes
            $labels->transform(function ($label) {
                $label->is_posted = $label->is_posted;
                $label->status_text = $label->status_text;
                return $label;
            });

            $pdfService = new LabelPdfService();
            return $pdfService->download($labels, "{$filename}.pdf");
        }
    }
}

