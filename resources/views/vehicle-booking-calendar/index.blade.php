@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

<style>
    .calendar-page {
        padding: 18px;
    }

    .calendar-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 22px;
        gap: 16px;
        flex-wrap: wrap;
    }

    .month-form {
        display: flex;
        gap: 14px;
        align-items: center;
        flex-wrap: wrap;
    }

    .month-form input,
    .month-form select {
        padding: 11px 14px;
        border: 1px solid #000;
        border-radius: 7px;
        font-size: 14px;
        background: #fff;
        min-width: 170px;
    }

    .month-form button,
    .csv-export-btn,
    .back-btn {
        padding: 11px 20px;
        color: #fff;
        border-radius: 7px;
        border: none;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }

    .month-form button {
        background: #198754;
    }

    .csv-export-btn {
        background: #6f42c1;
    }

    .back-btn {
        background: #343a40;
    }

    .month-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
        gap: 14px;
    }

    .month-nav a {
        padding: 11px 20px;
        background: #0d6efd;
        color: #fff;
        border-radius: 7px;
        font-weight: 700;
        text-decoration: none;
    }

    .month-title {
        font-size: 22px;
        font-weight: bold;
        text-align: center;
    }

    .calendar-wrapper {
        background: #fff;
        padding: 14px;
        border-radius: 8px;
    }

    #vehicleCalendarTable {
        border-collapse: collapse !important;
        width: 100% !important;
        table-layout: fixed;
    }

    #vehicleCalendarTable th,
    #vehicleCalendarTable td {
        border: 1px solid #000 !important;
        vertical-align: middle;
    }

    #vehicleCalendarTable th:first-child,
    #vehicleCalendarTable td:first-child {
        width: 140px;
    }

    .timeline-header,
    .timeline-grid {
        display: grid;
        grid-template-columns: repeat({{ $totalDays }}, 1fr);
        width: 100%;
    }

    .timeline-header div {
        font-size: 10px;
        padding: 5px 1px;
        text-align: center;
        border-right: 1px solid #000;
        font-weight: 700;
    }

    .timeline-grid {
        height: 32px;
        position: relative;
        overflow: hidden;
    }

    .day-bg {
        border-right: 1px solid #000;
        grid-row: 1;
    }

    .booking-range {
        height: 20px;
        font-size: 12px;
        line-height: 20px;
        text-align: center;
        margin: 6px 1px;
        z-index: 5;
        position: relative;
        white-space: nowrap;
        overflow: hidden;
    }

    .booking-bar {
        background: #00589c;
        color: #fff;
        font-weight: 700;
    }

    .vehicle-col {
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dataTables_wrapper {
        font-size: 12px;
    }

    .dataTables_filter,
    .dataTables_length {
        margin-bottom: 12px;
    }

    @media (max-width: 768px) {
        .month-nav,
        .calendar-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .month-title {
            order: -1;
        }
    }
</style>

<div class="container-fluid calendar-page">

    <div class="calendar-actions">
        <a href="{{ route('dashboard') }}" class="back-btn">Back</a>

        <form method="GET" action="{{ route('vehicle.booking.calendar') }}" class="month-form">
            <select name="type_id" onchange="this.form.submit()">
                <option value="all" {{ $selectedTypeId == 'all' ? 'selected' : '' }}>
                    All Types
                </option>

                @foreach($vehicleTypes as $type)
                    <option value="{{ $type->id }}" {{ $selectedTypeId == $type->id ? 'selected' : '' }}>
                        {{ $type->type_name }}
                    </option>
                @endforeach
            </select>

            <select name="category_id" onchange="this.form.submit()">
                <option value="all" {{ $selectedCategoryId == 'all' ? 'selected' : '' }}>
                    All Categories
                </option>

                @foreach($vehicleCategories as $category)
                    <option value="{{ $category->id }}" {{ $selectedCategoryId == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <input type="month" name="month" value="{{ $selectedMonth }}">

            <button type="submit">Go</button>
        </form>

        <button id="exportExcelBtn" class="csv-export-btn">
            Export Excel
        </button>
    </div>

    <div class="month-nav">
        <a href="{{ route('vehicle.booking.calendar', [
            'month' => $previousMonth,
            'type_id' => $selectedTypeId,
            'category_id' => $selectedCategoryId
        ]) }}">
            Previous
        </a>

        <div class="month-title">
            {{ $month->format('F Y') }}
            - {{ $selectedVehicleType ?? 'All Types' }}
            - {{ $selectedVehicleCategory ?? 'All Categories' }}
        </div>

        <a href="{{ route('vehicle.booking.calendar', [
            'month' => $nextMonth,
            'type_id' => $selectedTypeId,
            'category_id' => $selectedCategoryId
        ]) }}">
            Next
        </a>
    </div>

    <div class="calendar-wrapper">
        <table id="vehicleCalendarTable" class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>
                        <div class="timeline-header">
                            @foreach($days as $day)
                                <div>{{ $day->format('d') }}</div>
                            @endforeach
                        </div>
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach($calendar as $row)
                    <tr>
                        <td class="vehicle-col">
                            {{ $row['vehicle']->reg_no ?? 'Vehicle #' . $row['vehicle']->id }}
                        </td>

                        <td>
                            <div class="timeline-grid">
                                @foreach($days as $day)
                                    <div class="day-bg"></div>
                                @endforeach

                                @if(count($row['ranges']) > 0)
                                    @php
                                        $startDay = collect($row['ranges'])->min('start_day');
                                        $endDay = collect($row['ranges'])->max('end_day');
                                    @endphp

                                    <div class="booking-range booking-bar"
                                        style="grid-column: {{ $startDay }} / {{ $endDay + 1 }};">
                                        {{ $row['usagePercent'] }}%
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@php
    $calendarExportRows = collect();

    foreach ($calendar as $row) {
        $usagePercent = (float) $row['usagePercent'];

        if ($usagePercent <= 0) {
            $utilizationStatus = 'Not Utilized';
        } elseif ($usagePercent > 75) {
            $utilizationStatus = 'Excellent';
        } elseif ($usagePercent >= 65) {
            $utilizationStatus = 'Good';
        } elseif ($usagePercent >= 50) {
            $utilizationStatus = 'Fair';
        } else {
            $utilizationStatus = 'Under Utilized';
        }

        $calendarExportRows->push([
            'vehicle' => $row['vehicle']->reg_no ?? ('Vehicle #' . $row['vehicle']->id),
            'vehicle_type' => $row['vehicle']->vehicleType->type_name ?? '-',
            'vehicle_category' => $row['vehicle']->vehicleCategory->name ?? '-',
            'total_bookings' => count($row['ranges']),
            'used_days' => $row['usedDays'],
            'usage' => $row['usagePercent'] . '%',
            'utilization_status' => $utilizationStatus,
        ]);
    }
@endphp

<script>
    window.calendarExportRows = @json($calendarExportRows->values());

    window.calendarExportMeta = {
        month: @json($month->format('F Y')),
        vehicleType: @json($selectedVehicleType ?? 'All Types'),
        vehicleCategory: @json($selectedVehicleCategory ?? 'All Categories'),
        totalDays: @json($totalDays),
        filename: @json('vehicle_booking_calendar_' . $month->format('Y_m') . '.xlsx')
    };
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('exportExcelBtn');

    if (!btn) return;

    btn.addEventListener('click', function () {
        const rows = window.calendarExportRows || [];
        const meta = window.calendarExportMeta || {};

        const data = [
            ['Vehicle Booking Calendar Report'],
            [`Month: ${meta.month}`],
            [`Vehicle Type: ${meta.vehicleType}`],
            [`Vehicle Category: ${meta.vehicleCategory}`],
            [`Total Days In Month: ${meta.totalDays}`],
            [],
            ['Vehicle No', 'Vehicle Type', 'Vehicle Category', 'Total Bookings', 'Used Days', 'Usage %', 'Utilization Status']
        ];

        rows.forEach(r => {
            data.push([
                r.vehicle,
                r.vehicle_type,
                r.vehicle_category,
                r.total_bookings,
                r.used_days,
                r.usage,
                r.utilization_status
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(data);

        ws['!cols'] = [
            { wch: 18 },
            { wch: 18 },
            { wch: 22 },
            { wch: 16 },
            { wch: 12 },
            { wch: 12 },
            { wch: 22 }
        ];

        ws['!merges'] = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } },
            { s: { r: 1, c: 0 }, e: { r: 1, c: 6 } },
            { s: { r: 2, c: 0 }, e: { r: 2, c: 6 } },
            { s: { r: 3, c: 0 }, e: { r: 3, c: 6 } },
            { s: { r: 4, c: 0 }, e: { r: 4, c: 6 } }
        ];

        styleRow(ws, 0, 0, 6, {
            fill: solidFill('1F4E78'),
            font: { bold: true, sz: 16, color: { rgb: 'FFFFFF' } },
            alignment: { horizontal: 'center', vertical: 'center' }
        });

        for (let r = 1; r <= 4; r++) {
            styleRow(ws, r, 0, 6, {
                fill: solidFill('D9EAF7'),
                font: { bold: true, color: { rgb: '1F1F1F' } }
            });
        }

        styleRow(ws, 6, 0, 6, {
            fill: solidFill('2F75B5'),
            font: { bold: true, color: { rgb: 'FFFFFF' } },
            alignment: { horizontal: 'center', vertical: 'center' },
            border: fullBorder('1F1F1F')
        });

        for (let r = 7; r < 7 + rows.length; r++) {
            styleRow(ws, r, 0, 6, {
                fill: solidFill('FFFFFF'),
                font: { color: { rgb: '000000' } },
                border: fullBorder('D9D9D9'),
                alignment: { vertical: 'center' }
            });

            const statusRef = XLSX.utils.encode_cell({ r, c: 6 });
            const status = ws[statusRef]?.v || '';

            if (status === 'Excellent') {
                styleCell(ws, statusRef, {
                    fill: solidFill('00B050'),
                    font: { bold: true, color: { rgb: 'FFFFFF' } },
                    alignment: { horizontal: 'center' }
                });
            } else if (status === 'Good') {
                styleCell(ws, statusRef, {
                    fill: solidFill('C6EFCE'),
                    font: { bold: true, color: { rgb: '006100' } },
                    alignment: { horizontal: 'center' }
                });
            } else if (status === 'Fair') {
                styleCell(ws, statusRef, {
                    fill: solidFill('FFF2CC'),
                    font: { bold: true, color: { rgb: '7F6000' } },
                    alignment: { horizontal: 'center' }
                });
            } else if (status === 'Under Utilized') {
                styleCell(ws, statusRef, {
                    fill: solidFill('F4CCCC'),
                    font: { bold: true, color: { rgb: '9C0006' } },
                    alignment: { horizontal: 'center' }
                });
            } else {
                styleCell(ws, statusRef, {
                    fill: solidFill('D9D9D9'),
                    font: { bold: true, color: { rgb: '000000' } },
                    alignment: { horizontal: 'center' }
                });
            }
        }

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Booking Calendar');
        XLSX.writeFile(wb, meta.filename || 'vehicle_booking_calendar.xlsx');
    });

    function styleRow(ws, rowIndex, startCol, endCol, style) {
        for (let c = startCol; c <= endCol; c++) {
            const ref = XLSX.utils.encode_cell({ r: rowIndex, c });
            if (!ws[ref]) ws[ref] = { t: 's', v: '' };
            ws[ref].s = mergeStyle(ws[ref].s, style);
        }
    }

    function styleCell(ws, ref, style) {
        if (!ws[ref]) ws[ref] = { t: 's', v: '' };
        ws[ref].s = mergeStyle(ws[ref].s, style);
    }

    function mergeStyle(base, extra) {
        return {
            ...(base || {}),
            ...(extra || {}),
            font: {
                ...(base?.font || {}),
                ...(extra?.font || {})
            },
            fill: extra?.fill || base?.fill,
            alignment: {
                ...(base?.alignment || {}),
                ...(extra?.alignment || {})
            },
            border: {
                ...(base?.border || {}),
                ...(extra?.border || {})
            }
        };
    }

    function solidFill(rgb) {
        return {
            patternType: 'solid',
            fgColor: { rgb }
        };
    }

    function fullBorder(color) {
        return {
            top: { style: 'thin', color: { rgb: color } },
            bottom: { style: 'thin', color: { rgb: color } },
            left: { style: 'thin', color: { rgb: color } },
            right: { style: 'thin', color: { rgb: color } }
        };
    }
});
</script>

<script>
$(document).ready(function () {
    $('#vehicleCalendarTable').DataTable({
        paging: true,
        searching: true,
        ordering: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false
    });
});
</script>

@endsection