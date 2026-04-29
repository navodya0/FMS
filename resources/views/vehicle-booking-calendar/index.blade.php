@extends('layouts.app')

@section('content')

{{-- DataTables --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

<style>
    .calendar-page {
        padding: 15px;
    }

    .calendar-actions {
        display: flex;
        justify-content: end;
        align-items: center;
        margin-bottom: 15px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .month-form {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .month-form input {
        padding: 6px 10px;
        border: 1px solid #000;
        border-radius: 5px;
        font-size: 13px;
    }

    .month-form button,
    .csv-export-btn {
        padding: 7px 12px;
        background: #198754;
        color: #fff;
        border-radius: 5px;
        border: none;
        text-decoration: none;
        font-size: 13px;
        cursor: pointer;
    }

    .csv-export-btn {
        background: #6f42c1;
    }

    .month-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        gap: 10px;
    }

    .month-nav a {
        padding: 7px 12px;
        background: #0d6efd;
        color: #fff;
        border-radius: 5px;
        text-decoration: none;
        font-size: 13px;
    }

    .month-title {
        font-size: 20px;
        font-weight: bold;
        text-align: center;
    }

    .calendar-wrapper {
        background: #fff;
        padding: 12px;
        border-radius: 8px;
        overflow-x: auto;
    }

    #vehicleCalendarTable {
        border-collapse: collapse !important;
        border: 1px solid #000 !important;
        width: 100% !important;
    }

    #vehicleCalendarTable thead th,
    #vehicleCalendarTable tbody td {
        border: 1px solid #000 !important;
        padding: 0 !important;
        vertical-align: middle !important;
        background: #fff;
    }

    #vehicleCalendarTable thead th {
        font-weight: 700;
        text-align: left;
        padding: 8px !important;
        background: #f8f9fa;
        color: #000;
    }

    #vehicleCalendarTable tbody td.vehicle-col,
    #vehicleCalendarTable tbody td:nth-child(2) {
        padding: 8px !important;
    }

    .timeline-header,
    .timeline-grid {
        display: grid;
        grid-template-columns: repeat({{ $totalDays }}, 50px);
        min-width: {{ $totalDays * 50 }}px;
    }

    .timeline-header {
        border-left: 1px solid #000;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
    }

    .timeline-header div {
        font-size: 12px;
        line-height: 16px;
        padding: 7px 4px;
        font-weight: 700;
        text-align: center;
        background: #fff;
        border-right: 1px solid #000;
        color: #000;
    }

    .timeline-grid {
        position: relative;
        min-height: 44px;
        padding: 0;
        overflow: hidden;
        border-left: 1px solid #000;
    }

    .day-bg {
        border-right: 1px solid #000;
        grid-row: 1;
        height: 44px;
        min-height: 44px;
    }

    .booking-range {
        height: 28px;
        font-size: 16px;
        line-height: 28px;
        text-align: center;
        position: relative;
        z-index: 5;
        margin: 8px 1px;
        overflow: hidden;
        padding: 0 4px;
        white-space: nowrap;
        border-radius: 0;
    }

    .booking-bar {
        background: #007288;
        color: #fff;
        font-weight: 700;
        border: 1px solid #005b6d;
    }

    .vehicle-col {
        font-weight: 700;
        white-space: nowrap;
        font-size: 13px;
        color: #000;
    }

    .status-badge {
        color: #fff;
        padding: 5px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: capitalize;
        display: inline-block;
        min-width: 74px;
        text-align: center;
    }

    .status-active {
        background: #198754;
    }

    .status-disabled {
        background: #6c757d;
    }

    .status-freezed {
        background: #0dcaf0;
        color: #000;
    }

    table.dataTable {
        border-collapse: collapse !important;
    }

    table.dataTable.no-footer {
        border-bottom: 1px solid #000 !important;
    }

    table.dataTable td,
    table.dataTable th {
        border: 1px solid #000 !important;
        box-sizing: border-box;
    }

    .dataTables_wrapper {
        width: 100%;
        font-size: 12px;
    }

    .dataTables_filter,
    .dataTables_length {
        margin-bottom: 12px;
    }

    .dataTables_filter input,
    .dataTables_length select {
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 4px 8px;
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
        <form method="GET" action="{{ route('vehicle.booking.calendar') }}" class="month-form">
            <input type="month" name="month" value="{{ $selectedMonth }}">
            <button type="submit">Go</button>
        </form>

        <button type="button" id="exportExcelBtn" class="csv-export-btn">
            Export Excel
        </button>
    </div>

    <div class="month-nav">
        <a href="{{ route('vehicle.booking.calendar', ['month' => $previousMonth]) }}">
            Previous Month
        </a>

        <div class="month-title">
            {{ $month->format('F Y') }}
        </div>

        <a href="{{ route('vehicle.booking.calendar', ['month' => $nextMonth]) }}">
            Next Month
        </a>
    </div>

    <div class="calendar-wrapper">
        <table id="vehicleCalendarTable" class="table table-bordered table-sm nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>
                        <div class="timeline-header">
                            @foreach($days as $day)
                                <div>
                                    {{ $day->format('d') }}<br>
                                    {{ $day->format('D') }}
                                </div>
                            @endforeach
                        </div>
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach($calendar as $row)
                    <tr>
                        <td class="vehicle-col">
                            {{ $row['vehicle']->reg_no ?: 'Vehicle #' . $row['vehicle']->id }}
                        </td>

                        <td>
                            <span class="status-badge status-{{ $row['displayStatus'] }}">
                                {{ $row['displayStatus'] }}
                            </span>
                        </td>

                        <td>
                            <div class="timeline-grid">
                                @foreach($days as $day)
                                    <div class="day-bg" style="grid-column: {{ $day->day }};"></div>
                                @endforeach

                                @if(count($row['ranges']) > 0)
                                    @php
                                        $startDay = collect($row['ranges'])->min('start_day');
                                        $endDay = collect($row['ranges'])->max('end_day');
                                    @endphp

                                    <div class="booking-range booking-bar"
                                         style="
                                            grid-column: {{ $startDay }} / {{ $endDay + 1 }};
                                            grid-row: 1;
                                         ">
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
            'vehicle_status' => ucfirst($row['displayStatus']),
            'total_bookings' => count($row['ranges']),
            'usage' => $row['usagePercent'] . '%',
            'utilization_status' => $utilizationStatus,
        ]);
    }
@endphp

<script>
    window.calendarExportRows = @json($calendarExportRows->values());

    window.calendarExportMeta = {
        month: @json($month->format('F Y')),
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
                [`Total Days In Month: ${meta.totalDays}`],
                [],
                ['Vehicle No', 'Vehicle Status', 'Total Bookings', 'Usage %', 'Utilization Status']
            ];

            rows.forEach(r => {
                data.push([
                    r.vehicle,
                    r.vehicle_status,
                    r.total_bookings,
                    r.usage,
                    r.utilization_status
                ]);
            });

            data.push([]);
            data.push(['Status Rules']);
            data.push(['Above 75%', 'Excellent']);
            data.push(['65% - 75%', 'Good']);
            data.push(['50% - 64.99%', 'Fair']);
            data.push(['1% - 49.99%', 'Under Utilized']);
            data.push(['0%', 'Not Utilized']);

            const ws = XLSX.utils.aoa_to_sheet(data);

            ws['!cols'] = [
                { wch: 18 },
                { wch: 18 },
                { wch: 16 },
                { wch: 12 },
                { wch: 20 }
            ];

            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: 4 } },
                { s: { r: 1, c: 0 }, e: { r: 1, c: 4 } },
                { s: { r: 2, c: 0 }, e: { r: 2, c: 4 } }
            ];

            styleRow(ws, 0, 0, 4, {
                fill: solidFill('1F4E78'),
                font: { bold: true, sz: 16, color: { rgb: 'FFFFFF' } },
                alignment: { horizontal: 'center', vertical: 'center' }
            });

            styleRow(ws, 1, 0, 4, {
                fill: solidFill('D9EAF7'),
                font: { bold: true, color: { rgb: '1F1F1F' } }
            });

            styleRow(ws, 2, 0, 4, {
                fill: solidFill('D9EAF7'),
                font: { bold: true, color: { rgb: '1F1F1F' } }
            });

            styleRow(ws, 4, 0, 4, {
                fill: solidFill('2F75B5'),
                font: { bold: true, color: { rgb: 'FFFFFF' } },
                alignment: { horizontal: 'center', vertical: 'center' },
                border: fullBorder('1F1F1F')
            });

            for (let r = 5; r < 5 + rows.length; r++) {
                styleRow(ws, r, 0, 4, {
                    fill: solidFill('FFFFFF'),
                    font: { color: { rgb: '000000' } },
                    border: fullBorder('D9D9D9'),
                    alignment: { vertical: 'center' }
                });

                const statusRef = XLSX.utils.encode_cell({ r, c: 4 });
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

            const ruleStart = 5 + rows.length + 2;

            styleRow(ws, ruleStart, 0, 1, {
                fill: solidFill('1F4E78'),
                font: { bold: true, color: { rgb: 'FFFFFF' } }
            });

            for (let r = ruleStart + 1; r <= ruleStart + 5; r++) {
                styleRow(ws, r, 0, 1, {
                    border: fullBorder('D9D9D9')
                });
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
            scrollX: true,
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