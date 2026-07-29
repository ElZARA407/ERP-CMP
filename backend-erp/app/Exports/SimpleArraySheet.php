<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SimpleArraySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows
    ) {}

    public function collection(): Collection
    {
        $headings = $this->headings();

        if (empty($this->rows)) {
            return collect([]);
        }

        return collect($this->rows)->map(function ($row) use ($headings) {
            return collect($headings)
                ->mapWithKeys(fn ($heading) => [$heading => $row[$heading] ?? null])
                ->all();
        });
    }

    public function headings(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        return array_keys($this->rows[0]);
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}