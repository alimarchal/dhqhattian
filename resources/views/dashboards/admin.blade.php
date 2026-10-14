{{-- Management dashboard: today, month-to-date against last month, trends, peak times, top departments and the team. --}}
@php
    $change = fn (float $now, float $before, string $suffix = 'vs yesterday') => \App\Support\DashboardFormat::change($now, $before, $suffix);
    $rs = fn ($n) => number_format((float) $n);
@endphp
@php
    $user = auth()->user();
    $n = $data['now'];
    $m = $data['mtd'];
    $lm = $data['last_mtd'];
    [$chitTone, $chitText] = $change($n['chits'], $data['usual'], 'vs usual '.now()->format('l'));
    if ((int) now()->format('H') < 12 && $chitTone === 'down') { $chitTone = 'flat'; $chitText = 'day still early · usual '.number_format($data['usual']); }
    [$govtTone, $govtText] = $change($m['govt'], $lm['govt'], 'vs last month');
    [$mChitTone, $mChitText] = $change($m['chits'], $lm['chits'], 'vs last month');
    $entShare = $m['chits'] ? round($m['entitled'] / $m['chits'] * 100) : 0;
    $lmEntShare = $lm['chits'] ? round($lm['entitled'] / $lm['chits'] * 100) : 0;
    $hifShare = $m['collected'] ? round($m['hif'] / $m['collected'] * 100) : 0;
    $departments = collect($data['departments']);
    $maxDept = max(1, (int) ($departments->max('n') ?? 1));
    $topDept = $departments->first();
    $deptTotal30 = max(1, (int) $departments->sum('n'));
    $busiestDay = collect($data['weekday'])->sortDesc()->keys()->first();
    $quietDay = collect($data['weekday'])->filter(fn ($v) => $v > 0)->sort()->keys()->first();
    $govtDepartments = collect($data['govt_departments']);
    $tests = collect($data['tests']);
    $topGovt = $govtDepartments->first();
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $insights = array_values(array_filter([
        $data['usual'] > 0 ? ($n['chits'] >= $data['usual']
            ? "Today is already at or above a usual ".now()->format('l')." (".$rs($n['chits'])." chits against about ".$rs($data['usual']).")."
            : "Today: ".$rs($n['chits'])." chits so far against about ".$rs($data['usual'])." on a usual ".now()->format('l').".") : null,
        $lm['govt'] > 0 ? "Government revenue this month is Rs ".$rs($m['govt'])." — ".($m['govt'] >= $lm['govt'] ? 'up' : 'down')." ".abs(round(($m['govt'] - $lm['govt']) / $lm['govt'] * 100))."% on the same days of last month." : null,
        "Busiest hour is ".sprintf('%02d:00', $data['peak_hour'])." (last 30 days); ".$busiestDay." is the busiest weekday and ".$quietDay." the quietest — plan staffing around them.",
        $topDept ? $topDept['name']." takes ".round($topDept['n'] / $deptTotal30 * 100)."% of all chits in the top ten departments (last 30 days)." : null,
        $topGovt ? "Most entitled patients come from ".$topGovt['name']." (".$rs($topGovt['n'])." chits in 90 days)." : null,
    ]));
@endphp

<x-app-layout>
    @include('dashboards.style')

    <div class="ak-page" style="padding-top: 24px">
        <x-status-message />

        <section class="db-hero" aria-label="Overview">
            <div class="db-hero-row">
                <div>
                    <h1>{{ $greeting }}, {{ \Illuminate\Support\Str::of($user->name)->replaceMatches('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '')->trim()->before(' ') }}</h1>
                    <p>Hospital overview &middot;
                        @if ($data['last_chit_at']) last chit issued {{ \Carbon\Carbon::parse($data['last_chit_at'])->diffForHumans() }} @else no chits yet @endif
                    </p>
                </div>
                <div class="db-date">{{ now()->format('l') }}<b>{{ now()->format('d M Y') }}</b></div>
            </div>
            @if ($insights)
                <ul style="margin: 16px 0 0; padding: 0; list-style: none; display: grid; gap: 6px; position: relative; z-index: 1; font-size: 13.5px; color: #dbeafe">
                    @foreach ($insights as $line)<li>✦ {{ $line }}</li>@endforeach
                </ul>
            @endif
        </section>

        <h2 class="db-section">Today</h2>
        <section class="db-grid db-kpis" aria-label="Today">
            @include('dashboards.kpi', ['label' => 'Chits today', 'value' => $rs($n['chits']), 'tone' => '#1e40af', 'icon' => 'chit', 'badge' => $chitText, 'badgeTone' => $chitTone, 'hint' => ''])
            @include('dashboards.kpi', ['label' => 'Invoices today', 'value' => $rs($n['invoices']), 'tone' => '#0e7490', 'icon' => 'invoice', 'hint' => $rs($data['prev']['invoices']).' yesterday'])
            @include('dashboards.kpi', ['label' => 'Government share', 'prefix' => 'Rs ', 'value' => $rs($n['govt']), 'tone' => '#16a34a', 'icon' => 'money', 'hint' => 'collected after HIF'])
            @include('dashboards.kpi', ['label' => 'New patients', 'value' => $rs($data['patients']), 'tone' => '#4f46e5', 'icon' => 'users', 'hint' => $rs($data['admissions']).' admissions today'])
        </section>

        <h2 class="db-section">This month so far <span>vs the same days of last month</span></h2>
        <section class="db-grid db-kpis" aria-label="This month">
            @include('dashboards.kpi', ['label' => 'Chits', 'value' => $rs($m['chits']), 'tone' => '#1e40af', 'icon' => 'chit', 'badge' => $mChitText, 'badgeTone' => $mChitTone, 'hint' => ''])
            @include('dashboards.kpi', ['label' => 'Government revenue', 'prefix' => 'Rs ', 'value' => $rs($m['govt']), 'tone' => '#16a34a', 'icon' => 'money', 'badge' => $govtText, 'badgeTone' => $govtTone, 'hint' => ''])
            @include('dashboards.kpi', ['label' => 'HIF share', 'prefix' => 'Rs ', 'value' => $rs($m['hif']), 'tone' => '#d97706', 'icon' => 'gift', 'hint' => $hifShare.'% of Rs '.$rs($m['collected']).' collected'])
            @include('dashboards.kpi', ['label' => 'Entitled (free) patients', 'value' => $entShare.'%', 'tone' => '#0e7490', 'icon' => 'shield', 'hint' => $rs($m['entitled']).' chits · last month '.$lmEntShare.'%'])
        </section>

        <section class="db-grid db-two">
            <div class="db-card">
                <div class="db-card-head"><h2>Last 12 months</h2><span>chits (bars) and government revenue (line)</span></div>
                <div class="db-card-body"><div id="months-chart" class="db-chart"></div></div>
            </div>
            <div class="db-card">
                <div class="db-card-head"><h2>Revenue split, 12 months</h2><span>government vs HIF</span></div>
                <div class="db-card-body"><div id="split-chart" class="db-chart"></div></div>
            </div>
        </section>

        <section class="db-card" style="margin-top:16px">
            <div class="db-card-head"><h2>Last 30 days</h2><span>daily chits and government revenue</span></div>
            <div class="db-card-body"><div id="days-chart" style="min-height: 280px"></div></div>
        </section>

        <section class="db-grid db-two is-even">
            <div class="db-card">
                <div class="db-card-head"><h2>Busiest hours</h2><span>chits per hour, last 30 days</span></div>
                <div class="db-card-body"><div id="hours-chart" style="min-height: 260px"></div></div>
            </div>
            <div class="db-card">
                <div class="db-card-head"><h2>Busiest weekdays</h2><span>average chits per day, last 90 days</span></div>
                <div class="db-card-body"><div id="weekday-chart" style="min-height: 260px"></div></div>
            </div>
        </section>

        <section class="db-grid db-two is-even">
            <div class="db-card">
                <div class="db-card-head"><h2>Top departments</h2><span>last 30 days · chits and government revenue</span></div>
                <div class="db-card-body" style="padding: 8px 18px 16px">
                    @forelse ($departments as $dept)
                        <div style="margin-top: 12px">
                            <div style="display:flex; justify-content:space-between; gap:10px; font-size:13.5px">
                                <span>{{ $dept['name'] }}</span>
                                <span><b>{{ $rs($dept['n']) }}</b> <span class="ak-muted">· Rs {{ $rs($dept['g']) }}</span></span>
                            </div>
                            <div class="db-bar"><span style="width: {{ round($dept['n'] / $maxDept * 100) }}%"></span></div>
                        </div>
                    @empty
                        <div class="db-empty">No chits in the last 30 days.</div>
                    @endforelse
                </div>
            </div>
            <div class="db-card">
                <div class="db-card-head"><h2>Where entitled patients work</h2><span>top government departments, last 90 days</span></div>
                <div class="db-card-body"><div id="govt-chart" style="min-height: 330px"></div></div>
            </div>
        </section>

        <section class="db-grid db-two">
            <div class="db-card">
                <div class="db-card-head"><h2>Laboratory &amp; services income</h2><span>government revenue by category, last 90 days</span></div>
                <div class="db-card-body"><div id="tests-chart" style="min-height: 300px"></div></div>
            </div>
            <div class="db-card">
                <div class="db-card-head"><h2>Patients registered today</h2><span>{{ $rs($data['patients']) }} new · {{ $rs($data['patients_mtd']) }} this month</span></div>
                <div class="db-card-body">
                    @if ($data['patients'] > 0)
                        <div id="gender-chart" style="min-height: 150px"></div><div id="age-chart" style="min-height: 150px"></div>
                    @else
                        <div class="db-empty">No new patients registered yet today.</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="db-card" style="margin-top:16px">
            <div class="db-card-head"><h2>Team performance</h2><span>government revenue collected per user</span></div>
            <div class="db-card-body">
                @if (empty($data['team']))
                    <div class="db-empty">No activity recorded this month yet.</div>
                @else
                    <div style="overflow-x:auto">
                        <table class="db-table">
                            <thead><tr><th>User</th><th class="r">Chits today</th><th class="r">Invoices today</th><th class="r">Revenue today</th><th class="r">Chits (month)</th><th class="r">Invoices (month)</th><th class="r">Revenue (month)</th></tr></thead>
                            <tbody>
                                @foreach ($data['team'] as $row)
                                    <tr>
                                        <td><b>{{ $row['name'] }}</b></td>
                                        <td class="r">{{ $rs($row['chits_today']) }}</td><td class="r">{{ $rs($row['invoices_today']) }}</td><td class="r">Rs {{ $rs($row['govt_today']) }}</td>
                                        <td class="r">{{ $rs($row['chits_month']) }}</td><td class="r">{{ $rs($row['invoices_month']) }}</td><td class="r"><b>Rs {{ $rs($row['govt_month']) }}</b></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <h2 class="db-section">Reports</h2>
        <nav class="db-links" aria-label="Reports">
            @can('view daily reports') <a href="{{ route('reports.opd.reportDailyIPD') }}" class="db-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>Daily revenue</a> @endcan
            @can('view opd reports') <a href="{{ route('reports.opd.user-wise') }}" class="db-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z" /></svg>User-wise OPD</a> @endcan
            @can('view opd reports') <a href="{{ route('reports.opd.specialist-fees') }}" class="db-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>Specialist fees</a> @endcan
            @can('view reports') <a href="{{ route('reports.index') }}" class="db-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>All reports</a> @endcan
        </nav>
    </div>

    @push('scripts')
        <script src="{{ url('js/apexcharts.js') }}"></script>
        <script>
            const base = { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } };
            const grid = { borderColor: '#e2e8f0', strokeDashArray: 4 };
            const int = (v) => Math.round(v).toLocaleString();
            const rupees = (v) => 'Rs ' + Math.round(v).toLocaleString();

            new ApexCharts(document.querySelector('#months-chart'), {
                chart: { ...base, type: 'line', height: 300 },
                series: [{ name: 'Chits', type: 'column', data: @json($data['chits_monthly']) }, { name: 'Government revenue', type: 'line', data: @json($data['govt_monthly']) }],
                xaxis: { categories: @json($data['months']) }, colors: ['#93c5fd', '#16a34a'], stroke: { width: [0, 3], curve: 'smooth' },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } }, dataLabels: { enabled: false }, grid,
                yaxis: [{ min: 0, title: { text: 'Chits' }, labels: { formatter: int } }, { min: 0, opposite: true, title: { text: 'Rs' }, labels: { formatter: int } }],
                legend: { position: 'top', horizontalAlign: 'right' },
            }).render();

            new ApexCharts(document.querySelector('#split-chart'), {
                chart: { ...base, type: 'bar', height: 300, stacked: true },
                series: [{ name: 'Government', data: @json($data['govt_monthly']) }, { name: 'HIF', data: @json($data['hif_monthly']) }],
                xaxis: { categories: @json($data['months']) }, colors: ['#16a34a', '#f59e0b'], dataLabels: { enabled: false }, grid,
                plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } }, yaxis: { labels: { formatter: (v) => (v / 1000).toFixed(0) + 'k' } },
                legend: { position: 'top', horizontalAlign: 'right' }, tooltip: { y: { formatter: rupees } },
            }).render();

            new ApexCharts(document.querySelector('#days-chart'), {
                chart: { ...base, type: 'line', height: 280 },
                series: [{ name: 'Chits', type: 'area', data: @json($data['chits30']) }, { name: 'Government revenue', type: 'line', data: @json($data['govt30']) }],
                xaxis: { categories: @json($data['days30']), tickAmount: 10, labels: { rotate: 0 } }, colors: ['#1e40af', '#16a34a'], stroke: { width: [2, 3], curve: 'smooth' },
                fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: .3, opacityTo: .02 } }, dataLabels: { enabled: false }, grid,
                yaxis: [{ min: 0, title: { text: 'Chits' }, labels: { formatter: int } }, { min: 0, opposite: true, title: { text: 'Rs' }, labels: { formatter: int } }],
                legend: { position: 'top', horizontalAlign: 'right' },
            }).render();

            const peak = {{ $data['peak_hour'] }}; const hourLabels = @json($data['hour_labels']);
            new ApexCharts(document.querySelector('#hours-chart'), {
                chart: { ...base, type: 'bar', height: 260 }, series: [{ name: 'Chits', data: @json($data['hours']) }],
                xaxis: { categories: hourLabels.map((l) => l.slice(0, 2)), title: { text: 'hour of the day' } },
                colors: [({ dataPointIndex }) => (hourLabels[dataPointIndex] === String(peak).padStart(2, '0') + ':00' ? '#1e40af' : '#93c5fd')],
                plotOptions: { bar: { borderRadius: 3, columnWidth: '70%', distributed: false } }, dataLabels: { enabled: false }, grid,
                yaxis: { labels: { formatter: int } },
            }).render();

            new ApexCharts(document.querySelector('#weekday-chart'), {
                chart: { ...base, type: 'bar', height: 260 }, series: [{ name: 'Avg chits', data: @json(array_values($data['weekday'])) }],
                xaxis: { categories: @json(array_keys($data['weekday'])) }, colors: ['#0e7490'],
                plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } }, dataLabels: { enabled: true, formatter: int, style: { fontSize: '11px' } }, grid,
                yaxis: { labels: { formatter: int } },
            }).render();

            new ApexCharts(document.querySelector('#govt-chart'), {
                chart: { ...base, type: 'bar', height: 330 }, series: [{ name: 'Chits', data: @json($govtDepartments->pluck('n')->values()) }],
                xaxis: { categories: @json($govtDepartments->pluck('name')->values()) }, colors: ['#4f46e5'],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } }, dataLabels: { enabled: true, formatter: int }, grid,
            }).render();

            new ApexCharts(document.querySelector('#tests-chart'), {
                chart: { ...base, type: 'bar', height: 300 }, series: [{ name: 'Government revenue', data: @json($tests->map(fn ($t) => round($t['g']))->values()) }],
                xaxis: { categories: @json($tests->pluck('name')->values()) }, colors: ['#16a34a'],
                plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } }, dataLabels: { enabled: false }, grid,
                yaxis: { labels: { formatter: (v) => (v / 1000).toFixed(0) + 'k' } }, tooltip: { y: { formatter: rupees } },
            }).render();

            @if ($data['patients'] > 0)
                new ApexCharts(document.querySelector('#gender-chart'), {
                    chart: { ...base, type: 'donut', height: 170 }, series: @json(array_values($data['gender'])), labels: @json(array_keys($data['gender'])),
                    colors: ['#1e40af', '#db2777'], legend: { position: 'right' }, dataLabels: { enabled: false }, plotOptions: { pie: { donut: { size: '62%' } } },
                }).render();
                new ApexCharts(document.querySelector('#age-chart'), {
                    chart: { ...base, type: 'bar', height: 170 }, series: [{ name: 'Patients', data: @json(array_values($data['age'])) }],
                    xaxis: { categories: @json(array_keys($data['age'])) }, colors: ['#0e7490'], plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                    dataLabels: { enabled: true }, grid, yaxis: { show: false },
                }).render();
            @endif
        </script>
    @endpush
</x-app-layout>
