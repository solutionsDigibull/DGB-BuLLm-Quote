@extends('crm.layouts.app')
<style>
    #loader-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .loader {
        color: white;
        font-size: 20px;
        font-weight: bold;
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
                                <li class="breadcrumb-item"><a href="/crm"><i class="ik ik-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="#">{{ $title }}</a></li>
                                <li class="breadcrumb-item active" aria-current="page">{{ $subtitle }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('qw.store') }}" method="POST" enctype="multipart/form-data" data-activity-route="qw.store">
                                @csrf
                                <div class="row">

                                    <label for="project_id" class="form-label" hidden><strong>Project ID</strong></label>
                                    <input type="text" class="form-control" name="project_id" id="project_id"
                                        value="{{ $bomDetails->project_id }}" readonly hidden>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="project_name" class="form-label"><strong>Project
                                                    Name</strong></label>
                                            <input type="text" class="form-control" name="project_name" id="project_name"
                                                value="{{ $bomDetails->project->name ?? 'N/A' }}" readonly>
                                        </div>
                                    </div>

                                    <!-- BOM ID Field -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="bom_id" class="form-label"><strong>BOM ID</strong></label>
                                            <input type="text" class="form-control" name="bom_id" id="bom_id"
                                                value="{{ $bomDetails->id }}" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Upload Field -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="file" class="form-label"><strong>Upload File</strong></label>
                                            <input type="file" class="form-control" name="file" id="file"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Upload</button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="loader-container" style="display: none;">
                <img src="{{ asset('assets/img/bull-gif.gif') }}" alt="Loading..." id="loader-gif" width="500">
            </div>
        </div>
    </div>
@endsection

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('bomForm');
        const loaderContainer = document.getElementById('loader-container');
        const loaderGif = document.getElementById('loader-gif');
        const staticLoader = document.getElementById('static-loader');

        if (form && loaderContainer && loaderGif && staticLoader) {
            form.addEventListener('submit', function(e) {
                loaderContainer.style.display = 'flex';
                loaderGif.src = loaderGif.src;
                const gifDuration = 30000;
                setTimeout(() => {
                    loaderGif.style.display = 'none';
                    staticLoader.style.display = 'block';
                }, gifDuration);
            });
        }
    });
</script>
