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

    table th,
    table td {
        white-space: nowrap;
    }

    .icon-height-width {
        width: 24px;
        height: 24px;
    }

    .dt-scroll-head {
        overflow: auto !important;
        position: relative !important;
        border: 0px !important;
        width: 100% !important;
    } 
</style>
@section('content')
    <div class="main-content">
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

        @if (session('success'))
            <div class="alert alert-success">
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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                @permission('roles')
                    <button class="btn btn-success" onclick="window.location.href='/roles/create'">Create Role</button>
                @endpermission
            </div>
            <div class="card-body">
                <div class="dt-responsive">
                    <table id="myDataTables2" id="scr-vtr-dynamic"
                        class="table table-striped table-bordered nowrap  viewBomDetails">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Company ID</th>
                                <th>Version</th>
                                <th>Settings </th>

                                <th>Commodity Date</th>
                                <th>FG Count</th>
                                <th>MPN Count</th>
                                <th>CPN Count</th>
                                <th>Assembly Count</th>
                                <th>Commodity Count</th>
                                <th>Bom Uploaded Date</th>
                                <th>Sourcing Commit Date</th>
                                <th>RFQ Updated Date </th>
                                <th>Volume Count </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bom-data-sourceengineer">
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Company ID</th>
                                <th>Version</th>
                                <th>Settings </th>

                                <th>Commodity Date</th>
                                <th>FG Count</th>
                                <th>MPN Count</th>
                                <th>CPN Count</th>
                                <th>Assembly Count</th>
                                <th>Commodity Count</th>
                                <th>Bom Uploaded Date</th>
                                <th>Sourcing Commit Date</th>
                                <th>RFQ Updated Date </th>
                                <th>Volume Count </th>
                                <th>Actions</th>
                            </tr>
                        </tfoot>
                    </table>

                </div>
                <div id="loader" style="display:none;">
                    <img src="{{ asset('assets/img/report-generation.gif') }}" id="loaderGif" alt="Loading..." />
                </div>
            </div>
        </div>
    @endsection

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('bom-data-sourceengineer');
            // const loader = document.getElementById('loader');
            // const loaderGif = document.getElementById('loaderGif');

            const formatDate = (dateStr, isSourcingCommit = false) => {
                if (!dateStr || isNaN(new Date(dateStr))) {
                    return isSourcingCommit ? 'Pending' : 'N/A';
                }
                const date = new Date(dateStr);
                return `${String(date.getDate()).padStart(2, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${date.getFullYear()}`;
            };

            const initializeTooltips = () => {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                    '[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            };

            const handleDownload = (buttonClass) => {
                const buttons = document.querySelectorAll(`.${buttonClass}`);
                buttons.forEach(button => {
                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        loader.style.display = 'flex';

                        loaderGif.onload = function() {
                            setTimeout(() => {
                                window.location.href = button.href;
                                loader.style.display = 'none';
                            }, 1500);
                        };

                        if (loaderGif.complete) {
                            loaderGif.onload();
                        }
                    });
                });
            };

            const populateTable = (data) => {
                tbody.innerHTML = ''; // Clear existing data
                if (data.length === 0) {
                    tbody.innerHTML =
                        `<tr><td colspan="14" style="text-align: center;">No data available</td></tr>`;
                    return;
                }

                data.forEach((bom, index) => {
                    console.log(bom.show_matrix_link );
                    const rowNumber = index + 1;
                    const emailSentStatus = bom.email_sent !== 'Pending' ? bom.email_sent : 'Pending';

                    const row = `
                    
                <tr>
                    <td>${rowNumber}</td>
                    <td>${bom.project?.name || 'N/A'}</td>
                    <td>${bom.bom_details?.centum_id || 'N/A'}</td>
                    <td>${bom.version || 'N/A'}</td>
                
<td>
  ${bom.bom_details?.settings === 'OK' ? 'Available' : bom.bom_details?.settings === 'error' ? 'NOT AVAILABLE' : bom.bom_details?.settings || 'NOT AVAILABLE'}
</td>

                    <td>${formatDate(bom.sourcing_commit_date, true)}</td>
                    <td>${bom.bom_details?.total_fg_count || 0}</td>
                    <td>${bom.bom_details?.total_mpn_count || 0}</td>
                    <td>${bom.bom_details?.total_cpn_count || 0}</td>
                    <td>${bom.bom_details?.total_assembly_count || 0}</td>
                    <td>${bom.bom_details?.total_commodity_count || 0}</td>
                    <td>${formatDate(bom.created_at)}</td>
                    <td>${formatDate(bom.sourcing_commit_date, true)}</td>
                    <td>${emailSentStatus}</td>
                    <td>${bom.bom_details?.volume_count || 0}</td>
                    <td>
                        <a href="/bom/audit/${bom.id}" title="Audit Report" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="crm.BOM.BOM-SourceEngineer.auditBOM" >
                            <img src="{{ asset('assets/img/icons/auditreport.png') }}" alt="Audit Report" class="icon-height-width ">
                        </a>
                        <a href="/supplier-Configuration/${bom.id}" title="Config" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="supplierConfig.form" >
                            <img src="{{ asset('assets/img/icons/configAuto.png') }}" alt="Config" class="icon-height-width ">
                        </a>
                         ${bom.has_supplier_quote ? `
                                            <a href="/supplier-quote/${bom.project.id}/${bom.id}" title="Supplier Quote" data-bs-toggle="tooltip" class="ml-3 underlineicon" data-activity-route="supplier-quote.index">
                                                <img src="{{ asset('assets/img/icons/supplierQuote.png') }}" alt="Supplier Quote" class="icon-height-width ">
                                            </a>` : ''}
                        <a href="/bom/download/file/${bom.id}" title="Download and View" class="ml-3 downloadButton1 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="bom.download.file" >
                            <img src="{{ asset('assets/img/icons/download.png') }}" alt="Download and View" class="icon-height-width ">
                        </a>
                        <a href="/bom/version-history/${bom.id}" title="View Version History" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="bom.versionHistory" >
                            <img src="{{ asset('assets/img/icons/versions.png') }}" alt="View Version History" class="icon-height-width ">
                        </a>
                        <a href="/bom/${bom.id}/edit" title="Edit BOM" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="bom.edit" >
                            <img src="{{ asset('assets/img/icons/edit.png') }}" alt="Edit BOM" class="icon-height-width ">
                        </a>

                        ${bom.bom_details?.is_valid_bom ? `
                                        <a href="/qw/create/${bom.project.id}/${bom.id}" title="Upload QW" data-bs-toggle="tooltip" class="ml-3 underlineicon" data-activity-route="qw.create">
                                            <img src="{{ asset('assets/img/icons/upload.png') }}" alt="Upload QW" class="icon-height-width">
                                        </a>` : ''}
        ${bom.show_matrix_link ? `
    <a href="/matrixs-bom/${bom.id}" title="BOM Matrix" class="ml-3 underlineicon" data-bs-toggle="tooltip" data-bs-placement="top" data-activity-route="bom.matrix.export">
        <img src="{{ asset('assets/img/icons/matrixbom.png') }}" alt="BOM Matrix" class="icon-height-width">
    </a>
` : ''}

                    </td>
                </tr>`;

                    tbody.innerHTML += row;
                });

                initializeTooltips();
            };


            const fetchBOMData = () => {
                loader.style.display = 'flex';
                const baseURL = window.location.origin;
                console.log(baseURL);
                fetch(`${baseURL}/api/boms/view`, {
                        headers: {
                            'Authorization': `Bearer ${localStorage.getItem('token')}`,
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        populateTable(data);
                        $('#myDataTables2').DataTable({
                            scrollY: '500px',
                            order: [
                                [0, 'asc']
                            ],
                            scrollX: true,
                            scrollCollapse: true,
                            paging: true,
                            fixedColumns: true,
                            columnDefs: [{
                                targets: 11, // Sourcing Commit Date
                                type: 'date' // Specify the column type as date
                            }],
                            drawCallback: function(settings) {
                                // Re-number rows after each draw (e.g., sorting, pagination)
                                const api = this.api();
                                api.column(0).nodes().each(function(cell, i) {
                                    cell.innerHTML = i +
                                        1; // Number rows starting from 1
                                });
                            }
                        });
                        loader.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error fetching BOM data:', error);
                        tbody.innerHTML =
                            `<tr><td colspan="14" style="text-align: center;">Error fetching data. Please try again later.</td></tr>`;
                        loader.style.display = 'none';
                    });
            };

            fetchBOMData();
            handleDownload('downloadButton1');
        });
    </script>
