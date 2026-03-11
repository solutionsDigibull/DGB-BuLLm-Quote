@extends('crm.layouts.app')

<style>
    #loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    #loader img {
        max-width: 700px;
    }

   .supplier-spinner {
  display: inline-block;
  width: 16px; height: 16px;
  border: 2px solid rgba(0,0,0,0.2);
  border-top-color: rgba(0,0,0,0.7);
  border-radius: 50%;
  animation: supplier-spin 0.8s linear infinite;
  vertical-align: middle;
}
@keyframes supplier-spin { to { transform: rotate(360deg); } }
</style>

@section('content')
    <div class="main-content">
        @if (session('success'))
            <div class="alert alert-success" id="successMessage">
                {{ session('success') }}
            </div>
        @endif

        @if (session('bomErrors'))
            <div class="alert alert-danger" id="validationErrors">
                <ol>
                    @foreach (session('bomErrors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ol>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" id="errorMessage">
                {{ session('error') }}
            </div>
        @endif


        <!-- @if (session('error'))
    <div class="alert alert-danger" id="errorMessage">
                {{ session('error') }}
            </div>
    @endif

            @if ($errors->any())
    <div class="alert alert-danger" id="validationErrors">
                <ul>
                    @foreach ($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
                </ul>
            </div>
    @endif -->

        <div class="container-fluid">
            <div class="page-header">
                <div class="row align-items-end">
                    <div class="col-lg-8">
                        <div class="page-header-title d-flex align-items-center">
                            <img src="{{ asset(config('app.logo_icon_path')) }}" class="logoicon" alt="Icon">
                            <div>
                                <h5>{{ $title }}</h5>
                                <span>{{ $subtitle }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <nav class="breadcrumb-container" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="/crm"><i class="ik ik-home"></i></a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="/bom">{{ $title }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ $subtitle }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                @permission('roles')
                    <button class="btn btn-success" onclick="window.location.href='/roles/create'">Create Role</button>
                @endpermission
            </div>
            <div class="card-body">
                <div class="dt-responsive">
                    <table id="myDataTables" class="table table-striped table-bordered nowrap viewBomDetails">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>#</th>
                                <th>BOM ID</th>
                                <th>Project Name</th>
                                <th>Company ID</th>
                                <th>Version</th>
                                <th>BOM Status</th>
                                <th>BOM Export Status</th>
                                <th>QW Upload Status</th>
                                <th>Settings</th>
                                <th>Row</th>
                                <th>Empty Fields</th>
                                <th>Missing Headers</th>
                                <th>Complexity</th>
                                <th>BOM Readiness</th>
                                <th>Estimated TAT</th>
                                <th>Priority</th>
                                <th>Costed CPN</th>
                                <th>Uncosted CPN</th>
                                <th>Total FG</th>
                                <th>Total MPN</th>
                                <th>Total CPN</th>
                                <th>Total Assembly</th>
                                <th>Total Commodity</th>
                                <th>Bom Uploaded Date</th>
                                <th>RFQ Uploaded Date</th>
                                <th>Sourcing Commit Date</th>
                                <th>Commodity Uploaded Date</th>
                                <th>Proto Volume</th>
                                <th>Volume Count</th>
                              

                                
                            </tr>
                        </thead>
                        <tbody>

                            @foreach ($bomUploads as $bomUpload)
                            @php
// REMOVE: $bomDetails = $bomUpload->bomDetails; // ❌ no relation now

// Use flattened fields coming from the controller
$fieldsCount     = is_numeric($bomUpload->fields_count ?? null) ? (int) $bomUpload->fields_count : 'N/A';
$emptyFields     = is_numeric($bomUpload->empty_fields ?? null) ? (int) $bomUpload->empty_fields : 'N/A';
$nonemptyFields  = is_numeric($bomUpload->non_empty_fields ?? null) ? (int) $bomUpload->non_empty_fields : 'N/A';

$totalRows       = is_numeric($bomUpload->fields_count ?? null) ? (int) $bomUpload->fields_count : 0;

// Complexity
$complexity = 'Simple';
if ($totalRows > 50 && $totalRows <= 100) {
    $complexity = 'Medium';
} elseif ($totalRows > 100) {
    $complexity = 'Complex';
}

// Estimated TAT based on rows count
$estimatedTAT = now();
if ($totalRows <= 50) {
    $estimatedTAT = $estimatedTAT->addDays(3)->format('d-m-Y');
} elseif ($totalRows <= 100) {
    $estimatedTAT = $estimatedTAT->addDays(5)->format('d-m-Y');
} else {
    $estimatedTAT = $estimatedTAT->addDays(7)->format('d-m-Y');
}

// Names/versions from flattened fields
$projectName   = $bomUpload->project_name ?? 'N/A';
$latestVersion = $bomUpload->latest_version ?? 'N/A';

// Optional fields that may not be provided by controller; fall back gracefully
$settings = isset($bomUpload->settings) ? $bomUpload->settings : null;
$priority                = $bomUpload->priority ?? 'N/A';
$completionPercentage    = isset($bomUpload->completion_percentage)
                            ? rtrim(rtrim(number_format($bomUpload->completion_percentage, 2), '0'), '.')
                            : 'N/A';
$totalCommodityCount     = $bomUpload->total_commodity_count ?? 'N/A';
$protoVolume             = $bomUpload->proto ?? 'N/A';
$volumeCount             = $bomUpload->volume_count ?? 'N/A';

// Dates: controller already formats v1_upload_date/rfq_upload_date/commodity_date;
// sourcing_commit_date in controller is formatted too. If not, they’re raw and still shown as-is.
$v1Date        = $bomUpload->v1_upload_date ?? 'N/A';
$rfqDate       = $bomUpload->rfq_upload_date ?? 'N/A';
$commitDate    = $bomUpload->sourcing_commit_date ?? 'N/A';
$commodityDate = $bomUpload->commodity_date ?? 'N/A';
@endphp

                                <tr>
                                     <td>
                                        <a href="{{ route('bom.download.file', $bomUpload->id) }}" title="Download BOM"
                                            data-activity-route="bom.download.file"
                                            class="underlineicon " data-bs-toggle="tooltip" data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/download.png') }}" alt="Download BOM"
                                                class="icon-height-width">
                                        </a>
                                        <a href="{{ route('bom.floating_status.show', $bomUpload->id) }}" title="Workflow Status"
                                            data-activity-route="bom.floating_status.show"
                                            class="underlineicon" data-bs-toggle="tooltip" data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/floatingstatus.png') }}" alt="Workflow Status" class="ml-3 icon-height-width">
                                        </a>

                                        <a href="{{ route('crm.BOM.BOM-SourceEngineer.auditBOM', $bomUpload->id) }}"
                                            data-activity-route="crm.BOM.BOM-SourceEngineer.auditBOM"
                                            title="Audit Report" class="underlineicon " data-bs-toggle="tooltip"
                                            data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/auditreport2.png') }}" alt="Audit Report"
                                                class="ml-3 icon-height-width">
                                        </a>
                                        <a href="{{ url('/bom/version-history/' . $bomUpload->id) }}"
                                            data-activity-route="bom.versionHistory"
                                            title="Version History" class="underlineicon " data-bs-toggle="tooltip"
                                            data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/versions.png') }}" alt="Version History"
                                                class="ml-3 icon-height-width">
                                        </a>


                                        @if (Auth::user()->hasRole('Sourcing Engineer'))
                                            <a href="{{ route('rfq.create', $bomUpload->id) }}"
                                                data-activity-route="rfq.create"
                                                title="Request for Quotation" class="underlineicon "
                                                data-bs-toggle="tooltip" data-bs-placement="top">
                                                <img src="{{ asset('assets/img/icons/mail.png') }}" alt="RFQ"
                                                    class="ml-3 icon-height-width-1">
                                            </a>
                                        @endif

                                        @permission('edit_bom')
                                            <a href="{{ route('bom.edit', $bomUpload->id) }}" class="underlineicon"
                                                data-activity-route="bom.edit"
                                                title="Edit" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <img src="{{ asset('assets/img/icons/edit.png') }}" alt="Edit"
                                                    class="ml-3 icon-height-width">
                                            </a>
                                        @endpermission

                                        {{-- @permission('supplier_config_bom')
                                            <a href="{{ route('supplierConfig.form', ['bom_id' => $bomUpload->id]) }}"
                                                class="btn btn-primary underlineicon" data-bs-toggle="tooltip"
                                                data-bs-placement="top" title="Supplier Config">Config</a>
                                        @endpermission --}}

                                        <a href="{{ route('bom.audit.graph', $bomUpload->id) }}" class="underlineicon"
                                            data-activity-route="bom.audit.graph"
                                            title="Audit Graph" data-bs-toggle="tooltip" data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/graphIcon.png') }}" alt="Audit Graph"
                                                class="ml-3 icon-height-width">
                                        </a>
                                        
                                       @if ($bomUpload->bom_status == 'Ok')
                                            @if ($bomUpload->has_pending_supplier_jobs)
                                                <span class="supplier-spinner ml-3" title="Generating supplier RFQs…"></span>
                                            @elseif ($bomUpload->has_email_sent)
                                                <span class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" title="Supplier mails already sent">
                                                    <img src="{{ asset('assets/img/icons/mail.png') }}" alt="Config (disabled)"
                                                        class="icon-height-width opacity-50" style="pointer-events:none;filter:grayscale(100%);" />
                                                </span>
                                            @else
                                                <a href="{{ route('supplierConfig.form', $bomUpload->id) }}" title="Config"
                                                data-activity-route="supplierConfig.form"
                                                class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top">
                                                    <img src="{{ asset('assets/img/icons/mail.png') }}" alt="Config" class="icon-height-width" />
                                                </a>
                                            @endif
                                        @endif

                                        @if ($bomUpload->has_supplier_quote)
                                        <a href="/supplier-quote/{{ $bomUpload->project_id }}/{{ $bomUpload->id }}"
                                            data-activity-route="supplier-quote.index"
                                            title="Supplier Quote" data-bs-toggle="tooltip" class="ml-3 underlineicon">
                                            <img src="{{ asset('assets/img/icons/supplierQuote.png') }}" alt="Supplier Quote"
                                                class="icon-height-width">
                                        </a>
                                        @endif
                                        @if ($bomUpload->bom_status == 'Ok')
                                    @if ($bomUpload->project_id)
                                        <a href="/qw/create/{{ $bomUpload->project_id }}/{{ $bomUpload->id }}" title="Upload QW"
                                            data-activity-route="qw.create"
                                            data-bs-toggle="tooltip" class="ml-3 underlineicon">
                                            <img src="{{ asset('assets/img/icons/upload.png') }}" alt="Upload QW" class="icon-height-width">
                                        </a>
                                    @endif
                                        @endif

                                        {{-- @if ($bomUpload->show_matrix_link)
                                        <a href="/download/mb/{{ $bomUpload->id }}" title="BOM Matrix" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top">
                                            <img src="{{ asset('assets/img/icons/matrixbom.png') }}" alt="BOM Matrix" class="icon-height-width">
                                        </a>
                                        @endif --}}
                                        @if ($bomUpload->has_mb_export_ready)
                                            <a href="/download/mb/{{ $bomUpload->id }}"
                                                data-activity-route="bom.download"
                                                class="ml-3 underlineicon js-matrix-bom"
                                                data-bom-id="{{ $bomUpload->id }}"
                                                title="BOM Matrix" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <img src="{{ asset('assets/img/icons/matrixbom.png') }}" alt="BOM Matrix" class="icon-height-width">
                                            </a>
                                        @endif 

                                    </td>

                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $bomUpload->id }}</td>
                                    <td>{{ $projectName }}</td>
                                    <td>{{ $bomUpload->centum_id ?? 'N/A' }}</td>
                                    <td>{{ $latestVersion }}</td>
                                    <td>
                                        @if($bomUpload->bom_status === 'Ok')
                                            <span class="badge bg-dark">{{ $bomUpload->bom_status }}</span>
                                        @elseif($bomUpload->bom_status === 'Pending')
                                            <span class="badge bg-warning">{{ $bomUpload->bom_status }}</span>
                                        @else
                                            <a href="{{ route('bom.download.file', $bomUpload->id) }}">
                                                <span class="badge bg-danger">{{ $bomUpload->bom_status ?? 'Error' }}</span>
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                         {{-- Export status badge placeholder --}}
                                        <span class="ml-2 export-status-badge"
                                                id="exp-{{ $bomUpload->id }}"
                                                data-bom-id="{{ $bomUpload->id }}"></span>
                                    </td>
                                    <td>
                                             {{ $bomUpload->quotewin_status }}
                                </td>
                                   <td>
                                        @if (strtolower(trim($settings)) === 'ok')
                                            Available
                                        @elseif (strtolower(trim($settings)) === 'error')
                                            NOT AVAILABLE
                                        @else
                                            {{ $settings ?? 'NOT AVAILABLE' }}
                                        @endif
                                    </td>
                                    <td>{{ $fieldsCount }}</td>
                                    <td>{{ $emptyFields }}</td>
                                    <td>{{ $nonemptyFields }}</td>
                                    <td>{{ $complexity }}</td>
                                    <td>{{ $completionPercentage }}</td>
                                    <td>{{ $estimatedTAT }}</td>
                                    <td>{{ $priority }}</td>
                                    <td>{{ $bomUpload->costed_cpn_count ?? '0' }}</td>
                                    <td>{{ $bomUpload->uncosted_cpn_count ?? '0' }}</td>
                                    <td>{{ $bomUpload->total_fg_count ?? 'N/A' }}</td>
                                    <td>{{ $bomUpload->total_mpn_count ?? 'N/A' }}</td>
                                    <td>{{ $bomUpload->total_cpn_count ?? 'N/A' }}</td>
                                    <td>{{ $bomUpload->total_assembly_count ?? 'N/A' }}</td>
                                    <td>{{ $totalCommodityCount }}</td>
                                    <td>{{ $v1Date }}</td>
                                    <td>{{ $rfqDate }}</td>
                                    <td>{{ $commitDate }}</td>
                                    <td>{{ $commodityDate }}</td>
                                    <td>{{ $protoVolume }}</td>
                                    <td>{{ $volumeCount }}</td>

                                    
                                   

                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Action</th>
                                <th>#</th>
                                <th>BOM ID</th>
                                <th>Project Name</th>
                                <th>Company ID</th>
                                <th>Version</th>
                                <th>BOM Status</th>
                                <th>BOM Export Status</th>
                                <th>QW Upload Status</th>
                                <th>Settings</th>
                                <th>Row</th>
                                <th>Empty Fields</th>
                                <th>Missing Headers</th>
                                <th>Complexity</th>
                                <th>BOM Readiness</th>
                                <th>Estimated TAT</th>
                                <th>Priority</th>
                                <th>Costed CPN</th>
                                <th>Uncosted CPN</th>
                                <th>Total FG</th>
                                <th>Total MPN</th>
                                <th>Total CPN</th>
                                <th>Total Assembly</th>
                                <th>Total Commodity</th>
                                <th>Bom Uploaded Date</th>
                                <th>RFQ Uploaded Date</th>
                                <th>Sourcing Commit Date</th>
                                <th>Commodity Uploaded Date</th>
                                <th>Proto Volume</th>
                                <th>Volume Count</th>
                                
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="loader" style="display:none;">
                    <img src="{{ asset('assets/img/report-generation.gif') }}" id="loaderGif" alt="Loading..." />
                </div>
            </div>
        </div>

    </div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.downloadButton').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const downloadUrl = this.href;

                fetch(downloadUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(response => {
                    if (response.ok) {
                        window.location.href =
                        downloadUrl; // Redirect to trigger download
                    } else {
                        alert('Error during download.');
                    }
                }).catch(error => console.error('Error:', error));
            });
        });
    });


    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }

    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    const validationErrors = document.getElementById('validationErrors');
    if (validationErrors) {
        setTimeout(() => {
            validationErrors.style.display = 'none';
        }, 5000);
    }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const downloadableCells = document.querySelectorAll('.downloadable');

        downloadableCells.forEach(cell => {
            cell.addEventListener('click', function() {
                const downloadUrl = cell.getAttribute('data-download-url');

                if (downloadUrl) {
                    window.location.href = downloadUrl;
                }
            });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const matrixBomButtons = document.querySelectorAll('.generateMatrixBomButton');

        matrixBomButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bomId = button.getAttribute('data-id');
                const url = `/bom/generate-matrix/${bomId}`;

                // Show loader while generating the file
                document.getElementById('loader').style.display = 'flex';

                fetch(url)
                    .then(response => {
                        if (response.ok) {
                            return response.blob();
                        } else {
                            throw new Error('Error generating Matrix BOM.');
                        }
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = 'Matrix_BOM.xlsx';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                    })
                    .catch(error => {
                        console.error(error);
                        alert('Failed to generate Matrix BOM.');
                    })
                    .finally(() => {
                        document.getElementById('loader').style.display = 'none';
                    });
            });
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const POLL_MS = 6000; // general refresh
  const QUICK_MS = 3000; // faster loop after user clicks

  function badgeHtml(status, message) {
    const map = { pending:'secondary', generating:'info', ready:'success', failed:'danger' };
    const cls = map[status] || 'secondary';
    const text = (status || 'pending').toUpperCase();
    return `<span class="badge bg-${cls}" title="${(message||'').replace(/"/g,'&quot;')}">${text}</span>`;
  }

  async function getStatus(bomId) {
    const res = await fetch(`/bom/${bomId}/export/status`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) throw new Error('Status fetch failed');
    return res.json();
  }

  async function paintStatusOnce(el) {
    const bomId = el.getAttribute('data-bom-id');
    try {
      const data = await getStatus(bomId);
      el.innerHTML = badgeHtml(data.status, data.message);
      el.dataset.status = data.status;
    } catch (e) {
      el.innerHTML = badgeHtml('pending', 'Unable to reach status');
      el.dataset.status = 'pending';
    }
  }

  function startRowPolling(el) {
    const tick = async () => {
      await paintStatusOnce(el);
      const st = el.dataset.status;
      if (st !== 'ready' && st !== 'failed') {
        el._timer = setTimeout(tick, POLL_MS);
      }
    };
    tick();
  }

  // initial render + background polling
  document.querySelectorAll('.export-status-badge').forEach(el => {
    paintStatusOnce(el).then(() => startRowPolling(el));
  });

  // CLICK: check status, then navigate (NO fetch to the download route)
  document.querySelectorAll('.js-matrix-bom').forEach(a => {
    a.addEventListener('click', async function (e) {
      e.preventDefault();
      const bomId = this.getAttribute('data-bom-id');
      const statusEl = document.getElementById(`exp-${bomId}`);
      const downloadHref = this.href; // /download/mb/{id}

      // 1) Quick status check
      try {
        const data = await getStatus(bomId);
        if (data.status === 'ready') {
          // 2) Navigate normally (lets browser follow 302 to S3 and download)
          window.location.href = downloadHref;
          return;
        }
      } catch (_) {
        // fall through to quick polling
      }

      // Not ready → show generating + quick poll
      statusEl.innerHTML = badgeHtml('generating', 'Preparing your BOM Matrix…');
      statusEl.dataset.status = 'generating';

      const quickPoll = async () => {
        try {
          const data = await getStatus(bomId);
          statusEl.innerHTML = badgeHtml(data.status, data.message);
          statusEl.dataset.status = data.status;

          if (data.status === 'ready') {
            window.location.href = downloadHref; // normal navigation
            return;
          }
          if (data.status === 'failed') {
            return; // badge already shows FAILED
          }
        } catch (_) {
          // ignore and retry
        }
        setTimeout(quickPoll, QUICK_MS);
      };
      quickPoll();
    });
  });

  // If DataTables re-renders rows, rebind after draw
  if (window.jQuery && jQuery.fn.dataTable) {
    jQuery('#myDataTables').on('draw.dt', function () {
      document.querySelectorAll('.export-status-badge').forEach(el => {
        if (!el._timer) { paintStatusOnce(el).then(() => startRowPolling(el)); }
      });
    });
  }
});
</script>
